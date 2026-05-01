import { AUTH_CONSTANTS } from "@/features/auth/auth-constants"
import axios, { type AxiosRequestConfig, isAxiosError } from "axios"

function handleUnauthorized() {
  sessionStorage.removeItem(AUTH_CONSTANTS.TOKEN_KEY)
  sessionStorage.removeItem(AUTH_CONSTANTS.USER_KEY)
  window.location.href = "/login"
}

export const useApi = (requestConf?: AxiosRequestConfig) => {
  const token = sessionStorage.getItem(AUTH_CONSTANTS.TOKEN_KEY)

  const api = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL,
    headers: token ? { Authorization: `Bearer ${token}` } : {},
    ...requestConf,
  })

  api.interceptors.response.use(
    (response) => response,
    (error) => {
      if (isAxiosError(error) && error.response?.status === 401) handleUnauthorized()
      return Promise.reject(error)
    },
  )

  return {
    get: api.get.bind(api),
    post: api.post.bind(api),
    put: api.put.bind(api),
    patch: api.patch.bind(api),
    delete: api.delete.bind(api),
  }
}
