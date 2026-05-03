export interface AuthContextType {
  user: UserInfo | null
  token: string | null
  login: (token: string, user: UserInfo) => void
  logout: () => void
  isAdmin: boolean
}

export interface UserInfo {
  username: string
  display_name: string
  email: string
  roles: string[]
}
