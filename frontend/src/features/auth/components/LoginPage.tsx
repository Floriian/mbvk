import { zodResolver } from "@hookform/resolvers/zod"
import { isAxiosError } from "axios"
import { useState } from "react"
import { useForm } from "react-hook-form"
import { useNavigate } from "react-router-dom"
import { Button } from "@/components/ui/button"
import { signInSchema, type SignInFormValues } from "../auth-schema"
import { useAuth } from "../useAuth"

export function LoginPage() {
  const { signIn } = useAuth()
  const navigate = useNavigate()
  const [error, setError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<SignInFormValues>({ resolver: zodResolver(signInSchema) })

  const onSubmit = async (data: SignInFormValues) => {
    setError(null)
    try {
      await signIn(data)
      navigate("/auctions")
    } catch (err) {
      if (isAxiosError(err) && err.response?.status === 401) {
        setError("Invalid username or password.")
      } else {
        setError("Something went wrong. Please try again.")
      }
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-background">
      <div className="w-full max-w-sm space-y-6 rounded-lg border p-8 shadow-sm">
        <div className="space-y-1">
          <h1 className="text-2xl font-bold">Sign in</h1>
          <p className="text-sm text-muted-foreground">
            Enter your credentials to continue
          </p>
        </div>

        <form
          onSubmit={handleSubmit(onSubmit)}
          className="space-y-4"
          noValidate
        >
          <div className="space-y-1">
            <label className="text-sm font-medium" htmlFor="username">
              Username
            </label>
            <input
              id="username"
              {...register("username")}
              className="w-full rounded border px-3 py-2 text-sm focus:ring-2 focus:ring-ring focus:outline-none"
              placeholder="kovacs.janos"
              autoComplete="username"
            />
            {errors.username && (
              <p className="text-xs text-destructive">
                {errors.username.message}
              </p>
            )}
          </div>

          <div className="space-y-1">
            <label className="text-sm font-medium" htmlFor="password">
              Password
            </label>
            <input
              id="password"
              type="password"
              {...register("password")}
              className="w-full rounded border px-3 py-2 text-sm focus:ring-2 focus:ring-ring focus:outline-none"
              autoComplete="current-password"
            />
            {errors.password && (
              <p className="text-xs text-destructive">
                {errors.password.message}
              </p>
            )}
          </div>

          {error && <p className="text-sm text-destructive">{error}</p>}

          <Button type="submit" className="w-full" disabled={isSubmitting}>
            {isSubmitting ? "Signing in…" : "Sign in"}
          </Button>
        </form>
      </div>
    </div>
  )
}
