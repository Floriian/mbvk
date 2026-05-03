import { Badge } from "@/components/ui/badge"
import type { AuctionStatus } from "../auction-types"

export interface AuctionStatusBadgeProps {
  status: AuctionStatus
}

export function AuctionStatusBadge({ status }: AuctionStatusBadgeProps) {
  const statusLookupTable: Record<
    AuctionStatus,
    {
      label: string
      // these variants are defined in badge.tsx
      variant:
        | "default"
        | "secondary"
        | "destructive"
        | "outline"
        | "ghost"
        | "link"
        | "success"
    }
  > = {
    pending: { label: "Függőben", variant: "secondary" },
    active: { label: "Aktív", variant: "success" },
    closed: { label: "Lezárt", variant: "destructive" },
  }

  return (
    <Badge variant={statusLookupTable[status].variant}>
      {statusLookupTable[status].label}
    </Badge>
  )
}
