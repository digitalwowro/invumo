import * as React from "react"

import { cn } from "@/lib/utils"

function Textarea({ className, ...props }: React.ComponentProps<"textarea">) {
  return (
    <textarea
      data-slot="textarea"
      className={cn(
        "border-input placeholder:text-foreground-subtle selection:bg-selection selection:text-foreground flex field-sizing-content min-h-24 w-full min-w-0 resize-y rounded-md border bg-background px-3 py-2 text-base transition-colors outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:bg-surface-inset disabled:text-foreground-subtle md:text-sm",
        "focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background",
        "aria-invalid:border-danger-text aria-invalid:ring-danger-text",
        className
      )}
      {...props}
    />
  )
}

export { Textarea }
