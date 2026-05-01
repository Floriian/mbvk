import React, { createContext, useContext, useState } from "react"
import { AUTH_CONSTANTS } from "./auth-constants"

interface UserInfo {
  username: string
  display_name: string
  email: string
  roles: string[]
}

interface AuthContextType {
  user: UserInfo | null
  token: string | null
  login: (token: string, user: UserInfo) => void
  logout: () => void
  isAdmin: boolean
}

const AuthContext = createContext<AuthContextType | null>(null)

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [token, setToken] = useState<string | null>(() =>
    sessionStorage.getItem(AUTH_CONSTANTS.TOKEN_KEY)
  )
  const [user, setUser] = useState<UserInfo | null>(() => {
    const raw = sessionStorage.getItem(AUTH_CONSTANTS.USER_KEY)
    return raw ? (JSON.parse(raw) as UserInfo) : null
  })

  const login = (newToken: string, newUser: UserInfo) => {
    sessionStorage.setItem(AUTH_CONSTANTS.TOKEN_KEY, newToken)
    sessionStorage.setItem(AUTH_CONSTANTS.USER_KEY, JSON.stringify(newUser))
    setToken(newToken)
    setUser(newUser)
  }

  const logout = () => {
    sessionStorage.removeItem(AUTH_CONSTANTS.TOKEN_KEY)
    sessionStorage.removeItem(AUTH_CONSTANTS.USER_KEY)
    setToken(null)
    setUser(null)
  }

  return (
    <AuthContext.Provider
      value={{
        user,
        token,
        login,
        logout,
        isAdmin: user?.roles.includes("ROLE_ADMIN") ?? false,
      }}
    >
      {children}
    </AuthContext.Provider>
  )
}

export function useAuthContext() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error("useAuthContext must be used inside AuthProvider")
  return ctx
}
