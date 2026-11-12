# Azure Blob Storage

> **Names in the examples (`receipts/<year>/<id>.pdf`, the `receipts` container, the `GenerateReceipt` use case, etc.) are illustrative only.** Use names that fit the current project's domain (e.g., `invoices/`, `documents/`, `avatars/`). Copy the structure, not the identifiers.

File storage is an external service, so it lives behind a **port interface** in `infra/storage/` — not `infra/gateway/`, which is reserved for HTTP/REST services. The interface stays narrow and domain-friendly; the Azure-specific concerns (containers, SAS tokens, MIME types) stay inside the adapter.

## Driver

```bash
npm install @azure/storage-blob
```

The official SDK. It provides the `BlobServiceClient` for connection-string-based access and supports SAS-token signing for time-limited URLs.

## The interface

`infra/storage/BlobStorage.ts`:

```ts
export type UploadInput = {
    key: string;                    // logical path within the container, e.g. "receipts/2026/0001.pdf"
    contentType: string;            // MIME type
    body: Buffer | NodeJS.ReadableStream;
    metadata?: Record<string, string>;
};

export type SignedUrlInput = {
    key: string;
    expiresInSeconds: number;
};

export default interface BlobStorage {
    upload(input: UploadInput): Promise<{ key: string; url: string }>;
    download(key: string): Promise<Buffer>;
    delete(key: string): Promise<void>;
    signedUrl(input: SignedUrlInput): Promise<string>;
    exists(key: string): Promise<boolean>;
}
```

Properties of the interface:

- **`key` is logical** — application code thinks in terms of paths, not containers. The adapter knows which container.
- **`upload` returns the resulting URL** — saves the caller from constructing it.
- **No Azure types leak.** `Buffer`, `Stream`, `string` only.
- **Streams supported for upload** so large files (PDFs, images) don't have to be buffered in memory.

## The Azure adapter

```ts
import {
    BlobServiceClient,
    ContainerClient,
    StorageSharedKeyCredential,
    BlobSASPermissions,
    SASProtocol,
    generateBlobSASQueryParameters,
} from "@azure/storage-blob";
import BlobStorage, { UploadInput, SignedUrlInput } from "./BlobStorage";

export type AzureBlobConfig = {
    connectionString: string;
    container: string;
};

export class AzureBlobStorage implements BlobStorage {
    private client: BlobServiceClient;
    private container: ContainerClient;
    private accountName: string;
    private accountKey: string;

    constructor(config: AzureBlobConfig) {
        this.client = BlobServiceClient.fromConnectionString(config.connectionString);
        this.container = this.client.getContainerClient(config.container);
        const parsed = this.parseConnectionString(config.connectionString);
        this.accountName = parsed.accountName;
        this.accountKey  = parsed.accountKey;
    }

    async upload(input: UploadInput): Promise<{ key: string; url: string }> {
        const blob = this.container.getBlockBlobClient(input.key);
        if (Buffer.isBuffer(input.body)) {
            await blob.uploadData(input.body, {
                blobHTTPHeaders: { blobContentType: input.contentType },
                metadata: input.metadata,
            });
        } else {
            await blob.uploadStream(
                input.body,
                4 * 1024 * 1024,    // buffer size
                5,                  // max concurrency
                {
                    blobHTTPHeaders: { blobContentType: input.contentType },
                    metadata: input.metadata,
                },
            );
        }
        return { key: input.key, url: blob.url };
    }

    async download(key: string): Promise<Buffer> {
        const blob = this.container.getBlockBlobClient(key);
        const response = await blob.downloadToBuffer();
        return response;
    }

    async delete(key: string): Promise<void> {
        await this.container.getBlockBlobClient(key).deleteIfExists();
    }

    async exists(key: string): Promise<boolean> {
        return this.container.getBlockBlobClient(key).exists();
    }

    async signedUrl(input: SignedUrlInput): Promise<string> {
        const blob = this.container.getBlockBlobClient(input.key);
        const credential = new StorageSharedKeyCredential(this.accountName, this.accountKey);
        const expiresOn = new Date(Date.now() + input.expiresInSeconds * 1000);
        const sas = generateBlobSASQueryParameters(
            {
                containerName: this.container.containerName,
                blobName: input.key,
                permissions: BlobSASPermissions.parse("r"),
                protocol: SASProtocol.Https,
                startsOn: new Date(),
                expiresOn,
            },
            credential,
        ).toString();
        return `${blob.url}?${sas}`;
    }

    private parseConnectionString(cs: string): { accountName: string; accountKey: string } {
        const map = new Map(cs.split(";").filter(Boolean).map(p => {
            const i = p.indexOf("=");
            return [p.slice(0, i), p.slice(i + 1)] as const;
        }));
        const accountName = map.get("AccountName");
        const accountKey  = map.get("AccountKey");
        if (!accountName || !accountKey) throw new Error("Invalid Azure connection string");
        return { accountName, accountKey };
    }
}
```

Notes:

- **Adapter takes config in the constructor.** No `process.env` here. `api.ts` reads the connection string and container name and passes them in.
- **`uploadData` for buffers, `uploadStream` for streams.** PDFKit produces a stream; receipts and invoices should flow through `uploadStream` to avoid buffering megabytes in memory.
- **Container created once.** `BlobServiceClient.fromConnectionString` is cheap; `getContainerClient` is also cheap. Cache them at construction time.
- **`signedUrl` uses SAS tokens** scoped to a single blob with read-only permissions. For write/delete signed URLs, expand `BlobSASPermissions.parse(...)`.
- **`deleteIfExists`** is the idempotent variant; do not use `delete` (which throws on not-found).

## Usage from a use case

```ts
import { inject } from "../../infra/di/Registry";
import BlobStorage from "../../infra/storage/BlobStorage";
import PdfGenerator from "../../infra/pdf/PdfGenerator";

export default class GenerateReceipt {
    @inject("pdfGenerator") pdf!: PdfGenerator;
    @inject("blobStorage")  storage!: BlobStorage;

    async execute(input: Input): Promise<Output> {
        const pdfStream = this.pdf.generateReceipt(input);
        const key = `receipts/${input.year}/${input.receiptId}.pdf`;
        const { url } = await this.storage.upload({
            key,
            contentType: "application/pdf",
            body: pdfStream,
            metadata: { receiptId: input.receiptId, customerId: input.customerId },
        });
        const signedUrl = await this.storage.signedUrl({ key, expiresInSeconds: 3600 });
        return { storageUrl: url, downloadUrl: signedUrl };
    }
}
```

Notes:

- The use case knows nothing about Azure. It speaks `BlobStorage`.
- Returning both the canonical URL (`url`, useful for storing in DB) and a short-lived signed URL (for emailing the customer) covers the common case.
- The key naming convention (`<resource>/<year>/<id>.<ext>`) keeps blobs organized — Azure has no real folders, but tooling treats `/` as a separator.

## Composition root wiring

```ts
const blob = new AzureBlobStorage({
    connectionString: process.env.AZURE_STORAGE_CONNECTION_STRING!,
    container:        process.env.AZURE_STORAGE_CONTAINER!,
});
Registry.getInstance().register("blobStorage", blob);
```

For local dev, point the connection string at **Azurite** (the local Azure Storage emulator) so dev and CI never touch production storage.

## Testing

For unit tests of use cases that involve storage, register an in-memory fake:

```ts
class InMemoryBlobStorage implements BlobStorage {
    private blobs = new Map<string, { body: Buffer; contentType: string }>();

    async upload(input: UploadInput) {
        const body = Buffer.isBuffer(input.body) ? input.body : await streamToBuffer(input.body);
        this.blobs.set(input.key, { body, contentType: input.contentType });
        return { key: input.key, url: `mem://${input.key}` };
    }
    async download(key: string) {
        const b = this.blobs.get(key);
        if (!b) throw new Error("Blob not found");
        return b.body;
    }
    async delete(key: string) { this.blobs.delete(key); }
    async exists(key: string) { return this.blobs.has(key); }
    async signedUrl(input: SignedUrlInput) { return `mem://${input.key}?expires=${input.expiresInSeconds}`; }
}
```

For integration tests that need real Azure semantics (especially SAS), run against Azurite in Docker:

```yaml
services:
  azurite:
    image: mcr.microsoft.com/azure-storage/azurite
    ports: ["10000:10000"]
    command: azurite-blob --blobHost 0.0.0.0
```

The Azurite connection string is well-known and documented; CI uses it.

## Common mistakes

- **Buffering large uploads in memory** — use `uploadStream` for anything that could exceed a few MB.
- **Not setting `Content-Type`** — without it, browsers may download instead of inline-rendering PDFs.
- **Using long-lived signed URLs** — keep expiry under an hour for sensitive content.
- **Hardcoding the container name in adapter code** — always pass via config.
- **Calling `delete` (not `deleteIfExists`)** in idempotent flows — the throw on not-found is rarely what you want.
