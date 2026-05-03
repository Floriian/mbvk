import { createContext, useContext } from "react"
import type { AuthContextType } from "./auth-types"

export const AuthContext = createContext<AuthContextType | null>(null)

export function useAuthContext() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error("useAuthContext must be used inside AuthProvider")
  return ctx
}
