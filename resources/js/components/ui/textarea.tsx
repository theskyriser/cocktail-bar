import * as React from "react"

import { cn } from "@/lib/utils"

function Textarea({ className, ...props }: React.ComponentProps<"textarea">) {
  return (
    <textarea
      data-slot="textarea"
      className={cn(
        "border-input caret-primary placeholder:text-muted-foreground hover:border-foreground/45 focus-visible:border-primary aria-invalid:border-destructive flex field-sizing-content min-h-[90px] w-full resize-y rounded-md border bg-transparent px-2.5 py-1.5 text-sm transition-colors outline-none disabled:cursor-not-allowed disabled:opacity-45",
        className
      )}
      {...props}
    />
  )
}

export { Textarea }
