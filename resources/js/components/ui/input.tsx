import * as React from "react"

import { cn } from "@/lib/utils"

function Input({ className, type, ...props }: React.ComponentProps<"input">) {
  return (
    <input
      type={type}
      data-slot="input"
      className={cn(
        "border-input caret-primary file:text-foreground placeholder:text-muted-foreground selection:bg-primary/30 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-2.5 py-1.5 text-sm transition-colors outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-45",
        "hover:border-foreground/45 focus-visible:border-primary focus-visible:outline-offset-0",
        "aria-invalid:border-destructive",
        className
      )}
      {...props}
    />
  )
}

export { Input }
