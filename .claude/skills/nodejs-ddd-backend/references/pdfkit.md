# PDFKit

> **Names in the examples (`ReceiptInput`, `InvoiceInput`, `generateReceipt`, `generateInvoice`, etc.) are illustrative only.** The domain shapes shown (receipts, invoices) are examples of the pattern, not fixed contracts. Define the documents, fields, and method names that fit the current project. Copy the structure, not the identifiers.

PDF generation is wrapped in a **driver** (port-and-adapter) in `infra/pdf/`. Use cases depend on the `PdfGenerator` interface — the **port** — and never on PDFKit's API directly. The PDFKit implementation — `PdfKitGenerator` — is the **driver** that satisfies the port. Switching to another library (Puppeteer, `pdf-lib`, a cloud service like Adobe PDF Services) is a one-file code change plus a one-line composition change; no use case, controller, test, or domain file is touched.

See `architecture.md` for the general port-and-adapters principle. This file applies it to PDF specifically.

## Driver

```bash
npm install pdfkit
npm install -D @types/pdfkit
```

The `@types/pdfkit` package is community-maintained and good enough.

## The interface

`infra/pdf/PdfGenerator.ts`:

```ts
import { Readable } from "stream";

export type ReceiptInput = {
    receiptId: string;
    issuedAt: Date;
    customer: { name: string; document: string; email: string };
    items: { description: string; quantity: number; unitPrice: number }[];
    total: number;
    currency: string;
};

export type InvoiceInput = {
    invoiceNumber: string;
    issuedAt: Date;
    dueAt: Date;
    customer: { name: string; document: string; address: string };
    lines: { description: string; quantity: number; unitPrice: number; amount: number }[];
    subtotal: number;
    tax: number;
    total: number;
    currency: string;
};

export default interface PdfGenerator {
    generateReceipt(input: ReceiptInput): Readable;
    generateInvoice(input: InvoiceInput): Readable;
}
```

Properties:

- **One typed method per document kind.** Don't expose a generic `render(template, data)` — the input shape per document carries the contract.
- **Returns a `Readable` stream**, not a `Buffer`. Streams compose with the storage adapter (`uploadStream`) and avoid buffering the whole PDF in memory.
- **No PDFKit types leak.** Callers depend on `Readable` from Node's stdlib.

## The adapter

```ts
import PDFDocument from "pdfkit";
import { Readable } from "stream";
import PdfGenerator, { ReceiptInput, InvoiceInput } from "./PdfGenerator";

export class PdfKitGenerator implements PdfGenerator {

    generateReceipt(input: ReceiptInput): Readable {
        const doc = new PDFDocument({ size: "A4", margin: 50 });
        this.header(doc, "Receipt", input.receiptId, input.issuedAt);
        this.customer(doc, input.customer.name, input.customer.document);
        this.itemsTable(doc, input.items.map(i => ({
            description: i.description,
            quantity: i.quantity,
            unitPrice: i.unitPrice,
            amount: i.quantity * i.unitPrice,
        })));
        this.total(doc, input.total, input.currency);
        doc.end();
        return doc;
    }

    generateInvoice(input: InvoiceInput): Readable {
        const doc = new PDFDocument({ size: "A4", margin: 50 });
        this.header(doc, "Invoice", input.invoiceNumber, input.issuedAt);
        doc.text(`Due: ${input.dueAt.toISOString().slice(0, 10)}`, { align: "right" });
        this.customer(doc, input.customer.name, input.customer.document, input.customer.address);
        this.itemsTable(doc, input.lines);
        this.totals(doc, [
            ["Subtotal", input.subtotal],
            ["Tax",      input.tax],
            ["Total",    input.total],
        ], input.currency);
        doc.end();
        return doc;
    }

    private header(doc: PDFKit.PDFDocument, title: string, id: string, issuedAt: Date) {
        doc.fontSize(20).text(title, { align: "left" });
        doc.fontSize(10).fillColor("#666");
        doc.text(`#${id}`);
        doc.text(`Issued: ${issuedAt.toISOString().slice(0, 10)}`);
        doc.moveDown().fillColor("#000");
    }

    private customer(doc: PDFKit.PDFDocument, name: string, document: string, address?: string) {
        doc.fontSize(12).text("Bill to:", { underline: true });
        doc.fontSize(11).text(name);
        doc.text(document);
        if (address) doc.text(address);
        doc.moveDown();
    }

    private itemsTable(
        doc: PDFKit.PDFDocument,
        lines: { description: string; quantity: number; unitPrice: number; amount: number }[],
    ) {
        const top = doc.y;
        doc.fontSize(10).fillColor("#666");
        doc.text("Description", 50, top);
        doc.text("Qty",         300, top, { width: 50, align: "right" });
        doc.text("Unit",        360, top, { width: 80, align: "right" });
        doc.text("Amount",      450, top, { width: 80, align: "right" });
        doc.moveTo(50, top + 15).lineTo(545, top + 15).stroke();
        doc.fillColor("#000").moveDown();

        for (const line of lines) {
            const y = doc.y;
            doc.text(line.description, 50, y, { width: 240 });
            doc.text(String(line.quantity),                300, y, { width: 50, align: "right" });
            doc.text(line.unitPrice.toFixed(2),            360, y, { width: 80, align: "right" });
            doc.text(line.amount.toFixed(2),               450, y, { width: 80, align: "right" });
            doc.moveDown();
        }
    }

    private total(doc: PDFKit.PDFDocument, total: number, currency: string) {
        doc.moveDown();
        doc.fontSize(12).text(`Total: ${currency} ${total.toFixed(2)}`, { align: "right" });
    }

    private totals(
        doc: PDFKit.PDFDocument,
        rows: [string, number][],
        currency: string,
    ) {
        doc.moveDown();
        for (const [label, amount] of rows) {
            doc.fontSize(11).text(`${label}: ${currency} ${amount.toFixed(2)}`, { align: "right" });
        }
    }
}
```

Notes:

- **`new PDFDocument({...})` returns a `Readable` stream**. Passing it to `storage.upload({ body: doc })` works directly because `uploadStream` consumes it.
- **`doc.end()` must be called** before the stream finishes. Without it, the stream stays open and the upload hangs.
- **Layout helpers are private methods** — `header`, `customer`, `itemsTable`, `totals`. They keep each `generateXxx` method readable and isolate font / color choices.
- **Numbers are formatted at the boundary.** `toFixed(2)` for currency. Domain code carries pure numbers.

## Fonts and assets

PDFKit ships with Helvetica by default. To use a custom font:

```
src/infra/pdf/assets/fonts/Inter-Regular.ttf
src/infra/pdf/assets/fonts/Inter-Bold.ttf
```

Register in the adapter constructor:

```ts
constructor() {
    // Register at the document level when generating, e.g.:
    // doc.registerFont("Inter",      path.join(__dirname, "assets/fonts/Inter-Regular.ttf"));
    // doc.registerFont("Inter-Bold", path.join(__dirname, "assets/fonts/Inter-Bold.ttf"));
}
```

`assets/` lives inside `infra/pdf/` so the adapter owns its dependencies. Make sure `tsc`/Docker copies non-`.ts` files: add `"copyfiles": "copyfiles -u 1 'src/**/*.{ttf,otf,png,jpg,svg}' dist"` to `scripts.build`.

For embedded logos / signatures, use `doc.image(path, x, y, { width })`.

## Composition root wiring

```ts
Registry.getInstance().register("pdfGenerator", new PdfKitGenerator());
```

The adapter has no dependencies on the registry; it is registered as a leaf.

## Use case integration

```ts
export default class GenerateReceipt {
    @inject("pdfGenerator") pdf!: PdfGenerator;
    @inject("blobStorage")  storage!: BlobStorage;
    @inject("receiptRepository") repo!: ReceiptRepository;

    async execute(input: Input): Promise<Output> {
        const receipt = await this.repo.getById(input.receiptId);
        const stream = this.pdf.generateReceipt({
            receiptId: receipt.getId(),
            issuedAt:  receipt.getIssuedAt(),
            customer:  receipt.getCustomer(),
            items:     receipt.getItems(),
            total:     receipt.getTotal(),
            currency:  receipt.getCurrency(),
        });
        const key = `receipts/${receipt.getYear()}/${receipt.getId()}.pdf`;
        const { url } = await this.storage.upload({
            key,
            contentType: "application/pdf",
            body: stream,
        });
        return { url };
    }
}
```

The use case orchestrates: domain → PDF stream → storage. Neither PDFKit nor Azure is mentioned by name.

## Swapping the driver

The whole point of the interface is that the PDF library is a **driver** — a replaceable implementation behind a stable port. To swap PDFKit for a different library, follow the same three-step pattern every time.

### Step 1 — Write a new adapter against the same port

Say we want to switch from PDFKit to Puppeteer (render HTML templates to PDF with a headless browser). Add a new file alongside the existing adapter, or keep them in the same file:

`infra/pdf/PuppeteerPdfGenerator.ts`:

```ts
import puppeteer, { Browser } from "puppeteer";
import { Readable } from "stream";
import PdfGenerator, { ReceiptInput, InvoiceInput } from "./PdfGenerator";

export class PuppeteerPdfGenerator implements PdfGenerator {
    private browser: Browser | null = null;

    generateReceipt(input: ReceiptInput): Readable {
        return this.renderHtmlToPdfStream(this.receiptHtml(input));
    }

    generateInvoice(input: InvoiceInput): Readable {
        return this.renderHtmlToPdfStream(this.invoiceHtml(input));
    }

    private renderHtmlToPdfStream(html: string): Readable {
        const stream = new Readable({ read() {} });
        this.renderHtmlToPdfBuffer(html)
            .then(buffer => { stream.push(buffer); stream.push(null); })
            .catch(err   => { stream.destroy(err); });
        return stream;
    }

    private async renderHtmlToPdfBuffer(html: string): Promise<Buffer> {
        if (!this.browser) this.browser = await puppeteer.launch({ headless: true });
        const page = await this.browser.newPage();
        try {
            await page.setContent(html, { waitUntil: "networkidle0" });
            return await page.pdf({ format: "A4", printBackground: true });
        } finally {
            await page.close();
        }
    }

    private receiptHtml(input: ReceiptInput): string { /* template literal */ return ""; }
    private invoiceHtml(input: InvoiceInput): string { /* template literal */ return ""; }
}
```

The **port (`PdfGenerator`) does not change**. The input types (`ReceiptInput`, `InvoiceInput`) do not change. The return type (`Readable`) does not change.

### Step 2 — Change one line in the composition root

`src/api.ts`:

```diff
- import { PdfKitGenerator } from "./infra/pdf/PdfGenerator";
+ import { PuppeteerPdfGenerator } from "./infra/pdf/PuppeteerPdfGenerator";

- Registry.getInstance().register("pdfGenerator", new PdfKitGenerator());
+ Registry.getInstance().register("pdfGenerator", new PuppeteerPdfGenerator());
```

That's the entire swap. Deploy it.

### Step 3 — Confirm what did *not* change

After the swap, these files are untouched:

- Every use case that depends on `PdfGenerator` (`GenerateReceipt`, `GenerateInvoice`, …).
- Every controller that calls those use cases.
- Every test that uses an in-memory fake implementing `PdfGenerator`.
- Every domain entity.

If any of those files needed edits, the port was leaking — the interface exposed PDFKit-specific types, or a use case was calling PDFKit directly. Fix the leak before completing the swap.

### Running two drivers side by side

Sometimes you want both drivers available at once — e.g., PDFKit for fast server-side receipts and Puppeteer for pixel-perfect marketing PDFs. Register both in `api.ts` under different names:

```ts
Registry.getInstance().register("receiptPdf",  new PdfKitGenerator());
Registry.getInstance().register("invoicePdf",  new PuppeteerPdfGenerator());
```

Use cases `@inject` the one they need by key. The interface stays singular; only the Registry key disambiguates.

### Why this works

- The **port** (`PdfGenerator`) is expressed in domain-friendly terms — `generateReceipt(input): Readable` — not PDFKit types.
- The **driver** isolates every library-specific concern inside one file (`PdfKitGenerator.ts` or `PuppeteerPdfGenerator.ts`).
- The **composition root** is the only file that knows which driver is in use.

Break any of these three properties and swaps stop being one-line changes. Keeping them intact is the single reason to prefer the adapter layer over a direct `import "pdfkit"` in a use case.

## Testing

PDF rendering is hard to assert on byte-for-byte. Practical strategies:

- **Smoke test**: render the PDF, assert the buffer is non-empty and starts with `%PDF-`.
- **Snapshot test for inputs**: assert that the input passed to `generateReceipt` matches expectations (mock the gateway, capture the call).
- **Visual regression** (optional): render to PNG via `pdftoppm` in a separate test stage if layout changes need review.

```ts
test("renders a non-empty PDF", async () => {
    const gen = new PdfKitGenerator();
    const stream = gen.generateReceipt(sampleReceipt);
    const chunks: Buffer[] = [];
    for await (const chunk of stream) chunks.push(chunk as Buffer);
    const buffer = Buffer.concat(chunks);
    expect(buffer.length).toBeGreaterThan(0);
    expect(buffer.subarray(0, 4).toString()).toBe("%PDF");
});
```

## Common mistakes

- **Forgetting `doc.end()`** — the stream never closes, the upload hangs, the request times out.
- **Buffering the whole PDF** before upload (`Buffer.concat` over the stream). Defeats the streaming benefit; use the stream directly.
- **Mixing layout logic into use cases.** Layout belongs in the adapter; use cases pass typed data.
- **Calling `doc.font(path)` per page** without registering once. Slow and fragile; register once at the top of `generateXxx`.
- **Currency formatting via `toString()`** — use `toFixed(2)` (or a `Money` value object that knows its own formatting).
