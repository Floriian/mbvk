import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import type { AuctionFilters, AuctionStatus } from "../auction-types"
import { Input } from "@/components/ui/input"
import { useAuthContext } from "@/features/auth/use-auth-context"
import { Button } from "@/components/ui/button"

interface Props {
  onFiltersChange: (filters: AuctionFilters) => void
  filters: AuctionFilters
  onImportClick: () => void
}

export function AuctionSearchFilters({
  onFiltersChange,
  filters,
  onImportClick,
}: Props) {
  return (
    <div className="mb-4 flex items-center space-x-4">
      <Input
        placeholder="Esetszám"
        value={filters.case_no}
        onChange={(e) =>
          onFiltersChange({ ...filters, case_no: e.target.value })
        }
      />
      <Select
        value={filters.status}
        onValueChange={(value) =>
          onFiltersChange({ ...filters, status: value as AuctionStatus })
        }
      >
        <SelectTrigger>
          <SelectValue>Státusz</SelectValue>
        </SelectTrigger>
        <SelectContent>
          <SelectGroup>
            <SelectItem value={undefined}>Összes</SelectItem>
            <SelectItem value="active">Aktív</SelectItem>
            <SelectItem value="pending">Függőben</SelectItem>
            <SelectItem value="closed">Lezárva</SelectItem>
          </SelectGroup>
        </SelectContent>
      </Select>

      <Button variant="outline" onClick={() => onImportClick()}>
        Importálás
      </Button>
    </div>
  )
}
