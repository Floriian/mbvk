import React, { useState } from "react"
import { AUTH_CONSTANTS } from "./auth-constants"
import { AuthContext } from "./use-auth-context"

interface UserInfo {
  username: string
  display_name: string
  email: string
  roles: string[]
}

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
