import { useAuthContext } from "@/features/auth/use-auth-context"
import { Button } from "@/components/ui/button"
import { Navigation } from "./Navigation"

export function Header() {
  const { user, logout } = useAuthContext()

  return (
    <header className="border-b border-border bg-card">
      <div className="mx-auto flex h-14 max-w-7xl items-center justify-between px-4">
        <span className="text-sm font-semibold tracking-wide text-foreground">
          MBVK Aukció
        </span>

        {user && <Navigation />}

        <div className="flex items-center gap-3">
          <span className="text-sm text-muted-foreground">
            {user?.display_name ?? user?.username}
          </span>
          <Button variant="outline" size="sm" onClick={logout}>
            Kijelentkezés
          </Button>
        </div>
      </div>
    </header>
  )
}
