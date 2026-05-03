import { useApi } from "@/lib/api"
import type { SignInFormValues } from "./auth-schema"
import { useAuthContext } from "./use-auth-context"

export const useAuth = () => {
  const { post } = useApi()
  const { login, logout } = useAuthContext()

  const signIn = async (data: SignInFormValues) => {
    const response = await post<{
      token: string
      expires_at: string
      user: Parameters<typeof login>[1]
    }>("/auth/login", data)
    login(response.data.token, response.data.user)
    return response
  }

  return { signIn, logout }
}
