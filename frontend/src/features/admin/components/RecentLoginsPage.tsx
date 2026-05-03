import { useEffect, useState } from "react"
import type { RecentLogins } from "../admin-types"
import { useAdmin } from "../use-admin"
import {
  TableHeader,
  TableRow,
  TableHead,
  TableBody,
  TableCell,
  Table,
} from "@/components/ui/table"
import { Badge } from "@/components/ui/badge"

export function RecentLoginsPage() {
  const [loginHistory, setLoginHistory] = useState<RecentLogins | null>(null)
  const { getLoginHistory } = useAdmin()

  useEffect(() => {
    ;(async () => {
      try {
        const response = await getLoginHistory()
        setLoginHistory(response.data)
      } catch (error) {
        console.error("Failed to fetch login history:", error)
      }
    })()
  }, [getLoginHistory])

  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>Felhasználónév</TableHead>
          <TableHead>Időpont</TableHead>
          <TableHead>Állapot</TableHead>
          <TableHead>IP cím</TableHead>
          <TableHead>User Agent</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {loginHistory?.events.map((login, index) => (
          <TableRow key={index}>
            <TableCell>{login.username}</TableCell>
            <TableCell>{new Date(login.timestamp).toLocaleString()}</TableCell>
            <TableCell>
              <Badge variant={login.success ? "success" : "destructive"}>
                {login.success ? "Sikeres" : "Sikertelen"}
              </Badge>
            </TableCell>
            <TableCell>{login.ip}</TableCell>
            <TableCell>{login.user_agent}</TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )
}
