import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import type { Asset } from "../asset-types"
import { formatPrice } from "@/lib/utils"

interface Props {
  assets?: Asset[]
}

export function AssetsTable({ assets }: Props) {
  return (
    <Table>
      <TableHeader>
        <TableHead>Megnevezés</TableHead>
        <TableHead>Leírás</TableHead>
        <TableHead>Kategória</TableHead>
        <TableHead>Minimum ár</TableHead>
      </TableHeader>
      <TableBody>
        {assets?.map((asset) => (
          <TableRow className="cursor-pointer transition-colors duration-100 hover:bg-neutral-100">
            <TableCell>{asset.title}</TableCell>
            <TableCell>{asset.description}</TableCell>
            <TableCell>{asset.category}</TableCell>
            <TableCell>{formatPrice(+asset.min_price)}</TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )
}
