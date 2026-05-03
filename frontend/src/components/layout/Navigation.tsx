import { NavLink } from "react-router-dom"
import { cn } from "@/lib/utils"
import { useAuthContext } from "@/features/auth/use-auth-context"

const navLinkClass = ({ isActive }: { isActive: boolean }) =>
  cn(
    "text-sm font-medium transition-colors hover:text-foreground",
    isActive ? "text-foreground" : "text-muted-foreground"
  )

export function Navigation() {
  const { user } = useAuthContext()
  return (
    <nav className="border-b border-border bg-background">
      <div className="mx-auto flex h-10 max-w-7xl items-center gap-6 px-4">
        <NavLink to="/auctions" className={navLinkClass}>
          Aukciók
        </NavLink>

        {user?.roles.includes("ROLE_ADMIN") && (
          <NavLink to="/admin/logins" className={navLinkClass}>
            Bejelentkezések
          </NavLink>
        )}
      </div>
    </nav>
  )
}
