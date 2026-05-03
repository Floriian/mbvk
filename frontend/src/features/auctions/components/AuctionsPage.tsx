import type { PaginatedResponse } from "@/types/paginated-response-type"
import { useEffect, useRef, useState } from "react"
import { useNavigate } from "react-router-dom"
import { type Auction, type AuctionFilters } from "../auction-types"
import { useAuctions } from "../use-auctions"
import { AuctionsTable } from "./AuctionsTable"
import { AuctionSearchFilters } from "./AuctionSearchFilter"
import { Paginate } from "@/components/ui/pagination"

export function AuctionsPage() {
  const [auctions, setAuctions] = useState<PaginatedResponse<Auction> | null>(
    null
  )
  const [filters, setFilters] = useState<AuctionFilters>({
    case_no: undefined,
    status: undefined,
    page: 1,
  })
  const { getAuctions, importAuction } = useAuctions()
  const navigate = useNavigate()
  const ref = useRef<HTMLInputElement>(null)

  const onImportClick = () => {
    ref.current?.click()
  }
  const handleOnChange = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    if (file) {
      try {
        const formData = new FormData()
        formData.append("file", file)
        const res = await importAuction(formData)
        console.log(res)
        navigate(`/auctions/${res.data.id}`)
      } catch (e) {
        console.log(e.response)
        alert("Nem sikerült az importálás. Kérem próbálja újra.")
      }
    }
  }

  useEffect(() => {
    ;(async () => {
      const response = await getAuctions({ ...filters, limit: 10 })
      setAuctions(response.data)
    })()
  }, [filters])

  return (
    <div className="flex flex-col gap-4">
      <input
        ref={ref}
        type="file"
        className="hidden"
        accept=".xml"
        onChange={handleOnChange}
      />
      <AuctionSearchFilters
        onFiltersChange={setFilters}
        filters={filters}
        onImportClick={onImportClick}
      />
      <AuctionsTable auctions={auctions?.items || []} />
      <Paginate
        currentPage={filters.page!}
        lastPage={10}
        onPageChange={(page) => setFilters({ ...filters, page })}
      />
    </div>
  )
}
