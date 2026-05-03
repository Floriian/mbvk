import { useEffect, useState } from "react"
import type { Auction } from "../auction-types"
import { useAuctions } from "../use-auctions"
import { useNavigate, useParams } from "react-router-dom"
import { NotFound } from "@/components/not-found"
import { AssetsTable } from "@/features/assets/components/AssetsTable"
import { Button } from "@/components/ui/button"
import { useAuthContext } from "@/features/auth/use-auth-context"
import { ConfirmDialog } from "@/components/ui/confirm-dialog"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { toast } from "sonner"
import { isAxiosError } from "axios"

export function AuctionDetailsPage() {
  const [auction, setAuction] = useState<Auction | null>(null)
  const { getAuctionDetails, deleteAuction, updateAuctionStatus } =
    useAuctions()
  const navigate = useNavigate()
  const params = useParams<{ id: string }>()
  const { user } = useAuthContext()

  const handleDelete = async () => {
    if (!params.id) return

    try {
      await deleteAuction(+params.id)
      navigate("/auctions")
    } catch {
      alert("Failed to delete auction. Please try again.")
    }
  }

  const handleStatusChange = async (newStatus: string) => {
    if (!params.id) return

    try {
      await updateAuctionStatus(+params.id, newStatus)
      setAuction((prev) =>
        prev ? { ...prev, status: newStatus as Auction["status"] } : prev
      )
      toast.success("Sikeresen megváltoztattad az aukció státuszát.")
    } catch (e) {
      if (isAxiosError(e)) {
        toast.error(
          e.response?.data?.detail ||
            "Failed to update status. Please try again."
        )
      }
    }
  }

  useEffect(() => {
    ;(async () => {
      if (!params.id) return
      const response = await getAuctionDetails(+params.id)
      setAuction(response.data)
    })()
  }, [params.id])

  if (!auction) return <NotFound />

  return (
    <div className="w-full">
      {user?.roles.includes("ROLE_ADMIN") && (
        <div className="w-full justify-end">
          <ConfirmDialog
            cancelLabel="Mégsem"
            confirmLabel="Igen"
            description={`Biztosan törölni szeretnéd a ${auction.case_no} aukciót?`}
            onConfirm={handleDelete}
            title="Aukció törlése"
            trigger={<Button variant="destructive">Aukció törlése</Button>}
          />
        </div>
      )}

      <h1 className="text-3xl font-bold">{auction.case_no} </h1>
      <div className="flex items-center gap-4">
        <p className="text-lg text-muted-foreground">{auction.debtor}</p>
        <Select
          value={auction.status}
          onValueChange={handleStatusChange}
          disabled={auction.status === "closed"}
        >
          <SelectTrigger>
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="pending">Függőben</SelectItem>
            <SelectItem value="closed">Lezárt</SelectItem>
            <SelectItem value="active">Aktív</SelectItem>
          </SelectContent>
        </Select>
      </div>
      <AssetsTable assets={auction.assets} />
    </div>
  )
}
