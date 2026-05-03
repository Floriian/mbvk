import { Navigate, Route, Routes } from "react-router-dom"
import { AuthProvider } from "@/features/auth/auth-context"
import { LoginPage } from "./features/auth/components/LoginPage"
import { useAuthContext } from "./features/auth/use-auth-context"
import { Layout } from "./components/layout/Layout"
import { AuctionsPage } from "./features/auctions/components/AuctionsPage"
import { AuctionDetailsPage } from "./features/auctions/components/AuctionDetailsPage"
import { RecentLoginsPage } from "./features/admin/components/RecentLoginsPage"

function RequireAuth({ children }: { children: React.ReactNode }) {
  const { token } = useAuthContext()
  return token ? <Layout>{children}</Layout> : <Navigate to="/login" replace />
}

function AppRoutes() {
  const { token } = useAuthContext()

  return (
    <Routes>
      <Route path="/admin">
        <Route
          path="logins"
          element={
            <RequireAuth>
              <RecentLoginsPage />
            </RequireAuth>
          }
        />
      </Route>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/auctions">
        <Route
          index
          element={
            <RequireAuth>
              <AuctionsPage />
            </RequireAuth>
          }
        />
        <Route
          path=":id"
          element={
            <RequireAuth>
              <AuctionDetailsPage />
            </RequireAuth>
          }
        />
      </Route>
      <Route
        path="/"
        element={<Navigate to={token ? "/auctions" : "/login"} replace />}
      />
    </Routes>
  )
}

export function App() {
  return (
    <AuthProvider>
      <AppRoutes />
    </AuthProvider>
  )
}

export default App
