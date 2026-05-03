import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import type { Auction, AuctionStatus } from "../auction-types"
import { formatDate } from "@/lib/utils"
import { useNavigate } from "react-router-dom"
import { NotFound } from "@/components/not-found"
import { AuctionStatusBadge } from "./AuctionStatusBadge"

interface Props {
  auctions?: Auction[]
}

export function AuctionsTable({ auctions }: Props) {
  const navigate = useNavigate()

  if (!auctions || auctions.length === 0) {
    return <NotFound />
  }

  return (
    <Table>
      <TableHeader>
        <TableHead>Esetszám</TableHead>
        <TableHead>Adós</TableHead>
        <TableHead>Kezdés időpontja</TableHead>
        <TableHead>Tételszám</TableHead>
        <TableHead>Státusz</TableHead>
      </TableHeader>
      <TableBody>
        {auctions.map((auction) => (
          <TableRow
            className="cursor-pointer transition-colors duration-100 hover:bg-neutral-100"
            onClick={() => navigate(`/auctions/${auction.id}`)}
          >
            <TableCell>{auction.case_no}</TableCell>
            <TableCell>{auction.debtor}</TableCell>
            <TableCell>{formatDate(auction.starts_at)}</TableCell>
            <TableCell>{auction.asset_count}</TableCell>
            <TableCell>
              <AuctionStatusBadge status={auction.status as AuctionStatus} />
            </TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )
}
