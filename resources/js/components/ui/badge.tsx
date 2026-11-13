import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

const badgeVariants = cva(
  "inline-flex w-fit shrink-0 items-center justify-center gap-1 overflow-hidden rounded-[3px] border border-transparent px-2.5 py-[3px] text-[11px] tracking-[0.02em] whitespace-nowrap transition-colors aria-invalid:border-destructive [&>svg]:pointer-events-none [&>svg]:size-3",
  {
    variants: {
      variant: {
        default: "bg-accent-100 text-accent-800 [a&]:hover:bg-accent-200",
        secondary:
          "bg-neutral-100 text-neutral-800 [a&]:hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-100",
        destructive:
          "bg-destructive/12 text-destructive [a&]:hover:bg-destructive/20",
        outline:
          "border-primary text-primary [a&]:hover:bg-primary/10 bg-transparent",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
)

function Badge({
  className,
  variant,
  asChild = false,
  ...props
}: React.ComponentProps<"span"> &
  VariantProps<typeof badgeVariants> & { asChild?: boolean }) {
  const Comp = asChild ? Slot : "span"

  return (
    <Comp
      data-slot="badge"
      className={cn(badgeVariants({ variant }), className)}
      {...props}
    />
  )
}

export { Badge, badgeVariants }
