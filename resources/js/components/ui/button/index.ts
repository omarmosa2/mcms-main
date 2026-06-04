import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Button } from "./Button.vue"

export const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-xl text-sm font-medium transition-all duration-200 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:ring-2 focus-visible:ring-[#0EA5E9]/35",
  {
    variants: {
      variant: {
        default:
          "bg-[#0EA5E9] text-white shadow-[0_10px_24px_-16px_rgb(14_165_233_/_0.75)] hover:bg-[#0284C7]",
        destructive:
          "bg-[#DC2626] text-white shadow-[0_10px_24px_-16px_rgb(220_38_38_/_0.6)] hover:bg-[#B91C1C]",
        outline:
          "border border-[#DDE9F3] bg-white text-[#1A2B3F] shadow-[0_1px_2px_rgb(15_42_71_/_0.05)] hover:border-[#BFE3F5] hover:bg-[#F7FBFE]",
        secondary:
          "bg-[#EAF7FE] text-[#075985] hover:bg-[#D6F0FC]",
        ghost:
          "text-[#1A2B3F] hover:bg-[#EAF7FE] hover:text-[#075985]",
        link: "text-[#0284C7] underline-offset-4 hover:underline",
        clay:
          "border border-[#DDE9F3] bg-[#FBFDFF] text-[#1A2B3F] shadow-[inset_0_1px_0_rgb(255_255_255_/_0.9),0_1px_2px_rgb(15_42_71_/_0.06)] hover:border-[#BFE3F5] hover:bg-white",
        neumorphic:
          "border border-[#DDE9F3] bg-[#FBFDFF] text-[#1A2B3F] shadow-[inset_0_1px_0_rgb(255_255_255_/_0.9),0_10px_24px_-22px_rgb(15_42_71_/_0.34)] hover:border-[#BFE3F5] hover:bg-white",
      },
      size: {
        "default": "h-9 px-4 py-2",
        "sm": "h-8 rounded-lg gap-1.5 px-3 text-xs",
        "lg": "h-10 rounded-xl px-5",
        "icon": "size-9 rounded-xl",
        "icon-sm": "size-8 rounded-lg",
        "icon-lg": "size-10 rounded-xl",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  },
)
export type ButtonVariants = VariantProps<typeof buttonVariants>
