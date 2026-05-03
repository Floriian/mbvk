export interface RecentLogins {
  events: LoginEvent[]
  count: number
}

export interface LoginEvent {
  timestamp: string
  username: string
  success: boolean
  ip: string
  user_agent: string
}
