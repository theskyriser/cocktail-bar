import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

const buttonVariants = cva(
  "font-heading inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md border border-transparent bg-transparent text-sm leading-tight font-semibold whitespace-nowrap transition-colors outline-none disabled:pointer-events-none disabled:opacity-45 aria-invalid:border-destructive [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
  {
    variants: {
      variant: {
        default:
          "text-primary border-primary hover:bg-primary/12 active:bg-primary/22",
        secondary:
          "text-foreground border-border hover:bg-foreground/7 active:bg-foreground/14",
        outline:
          "text-foreground border-border hover:bg-foreground/7 active:bg-foreground/14",
        destructive:
          "text-destructive border-destructive hover:bg-destructive/10 active:bg-destructive/20",
        ghost:
          "text-primary hover:bg-primary/10 active:bg-primary/18 px-1 has-[>svg]:px-1",
        link: "text-primary underline-offset-3 hover:underline",
      },
      size: {
        default: "h-9 px-4 py-2 has-[>svg]:px-3",
        sm: "h-8 px-3 has-[>svg]:px-2.5",
        lg: "h-10 px-6 has-[>svg]:px-4",
        icon: "size-9 p-0",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  }
)

function Button({
  className,
  variant,
  size,
  asChild = false,
  ...props
}: React.ComponentProps<"button"> &
  VariantProps<typeof buttonVariants> & {
    asChild?: boolean
  }) {
  const Comp = asChild ? Slot : "button"

  return (
    <Comp
      data-slot="button"
      className={cn(buttonVariants({ variant, size, className }))}
      {...props}
    />
  )
}

export { Button, buttonVariants }
