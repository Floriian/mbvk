import type { Asset } from "../assets/asset-types"

export type AuctionStatus = "pending" | "active" | "closed"

export interface Auction {
  id: number
  case_no: string
  debtor: string
  starts_at: string
  status: AuctionStatus
  asset_count: number
  assets?: Asset[]
}

export interface GetAuctionsRequest {
  status?: AuctionStatus
  case_no?: string
  page?: number
  limit?: number
}

export interface AuctionFilters {
  case_no?: string
  status?: AuctionStatus
  page?: number
}
