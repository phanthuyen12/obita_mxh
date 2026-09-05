import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Badge } from "./Badge.vue"

// Indies sticker badge — `border-2 border-foreground` + uppercase
// `tracking-widest` + ink offset shadow, mirrors the eyebrow / channel
// badge pattern from the marketing site (NetworksGrid, PricingTables).
export const badgeVariants = cva(
  "inline-flex items-center justify-center w-fit shrink-0 whitespace-nowrap rounded-md px-2 py-0.5 text-xs font-medium gap-1.5 transition-colors overflow-hidden [&>svg]:size-3 [&>svg]:pointer-events-none",
  {
    variants: {
      variant: {
        default:
          "bg-primary/20 text-amber-950 font-medium [a&]:hover:bg-primary/30",
        secondary:
          "bg-secondary text-secondary-foreground [a&]:hover:bg-secondary/80",
        destructive:
          "bg-red-50 text-red-700 border border-red-200/80 [a&]:hover:bg-red-100",
        success:
          "bg-emerald-50 text-emerald-700 border border-emerald-200/80 [a&]:hover:bg-emerald-100",
        warning:
          "bg-amber-50 text-amber-800 border border-amber-200/80 [a&]:hover:bg-amber-100",
        info:
          "bg-blue-50 text-blue-700 border border-blue-200/80 [a&]:hover:bg-blue-100",
        outline:
          "border border-border text-foreground/80 bg-card [a&]:hover:bg-accent",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
)
export type BadgeVariants = VariantProps<typeof badgeVariants>
