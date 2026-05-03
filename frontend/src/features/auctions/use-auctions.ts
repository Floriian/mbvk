import { useApi } from "@/lib/api"
import { type Auction, type GetAuctionsRequest } from "./auction-types"
import { type PaginatedResponse } from "@/types/paginated-response-type"

export function useAuctions() {
  const { get, post, del, patch } = useApi()

  const getAuctions = async (query: GetAuctionsRequest) => {
    return get<PaginatedResponse<Auction>>("/auctions", { params: query })
  }

  const getAuctionDetails = async (id: number) => {
    return get<Auction>(`/auctions/${id}`)
  }

  const importAuction = async (formData: FormData) => {
    return post("/auctions/import", formData)
  }

  const deleteAuction = async (id: number) => {
    return del(`/auctions/${id}`)
  }

  const updateAuctionStatus = async (id: number, status: string) => {
    return patch(`/auctions/${id}`, { status })
  }

  return {
    getAuctions,
    getAuctionDetails,
    importAuction,
    deleteAuction,
    updateAuctionStatus,
  }
}
