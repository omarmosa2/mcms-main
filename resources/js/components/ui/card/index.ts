import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Card } from "./Card.vue"
export { default as CardAction } from "./CardAction.vue"
export { default as CardContent } from "./CardContent.vue"
export { default as CardDescription } from "./CardDescription.vue"
export { default as CardFooter } from "./CardFooter.vue"
export { default as CardHeader } from "./CardHeader.vue"
export { default as CardTitle } from "./CardTitle.vue"

export const cardVariants = cva(
  "text-card-foreground flex flex-col gap-6 rounded-xl border py-6",
  {
    variants: {
      variant: {
        default: "bg-card shadow-sm",
        flat: "pattern-surface-flat",
        minimal: "pattern-surface-minimal",
        clay: "pattern-card-clay",
        editorial: "pattern-editorial-block",
        monochrome: "pattern-data-monochrome",
        glass: "pattern-alert-glass",
        critical: "pattern-alert-critical",
        organic: "pattern-flow-organic",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
)

export type CardVariants = VariantProps<typeof cardVariants>
