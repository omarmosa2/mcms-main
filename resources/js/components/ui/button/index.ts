import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Button } from "./Button.vue"

export const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-lg text-sm transition-all duration-200 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:ring-2 focus-visible:ring-[#1D9E75]/50",
  {
    variants: {
      variant: {
        default:
          "bg-[#1D9E75] text-white hover:bg-[#0F6E56]",
        destructive:
          "bg-[#DC2626] text-white hover:bg-[#B91C1C]",
        outline:
          "border border-[#E5E7EB] bg-white text-[#1A1A1A] hover:bg-[#F9FAFB] hover:text-[#1A1A1A]",
        secondary:
          "bg-[#F7F8FA] text-[#1A1A1A] hover:bg-[#E5E7EB]",
        ghost:
          "hover:bg-[#F9FAFB] hover:text-[#1A1A1A]",
        link: "text-[#1D9E75] underline-offset-4 hover:underline",
      },
      size: {
        "default": "h-9 px-4 py-2",
        "sm": "h-8 rounded-md gap-1.5 px-3 text-xs",
        "lg": "h-10 rounded-lg px-5",
        "icon": "size-9 rounded-md",
        "icon-sm": "size-8 rounded-md",
        "icon-lg": "size-10 rounded-lg",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  },
)
export type ButtonVariants = VariantProps<typeof buttonVariants>
