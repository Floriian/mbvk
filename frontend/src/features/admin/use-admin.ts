import { useApi } from "@/lib/api"

export function useAdmin() {
  const { get } = useApi()

  const getLoginHistory = async () => {
    return await get("/auth/recent-logins")
  }

  return {
    getLoginHistory,
  }
}
