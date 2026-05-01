import { Navigate, Route, Routes } from "react-router-dom"
import { AuthProvider, useAuthContext } from "@/features/auth/auth-context"
import { LoginPage } from "./features/auth/components/LoginPage"

function RequireAuth({ children }: { children: React.ReactNode }) {
  const { token } = useAuthContext()
  return token ? <>{children}</> : <Navigate to="/login" replace />
}

function AppRoutes() {
  const { token } = useAuthContext()

  return (
    <Routes>
      <Route path="/auth">
        <Route path="login" element={<LoginPage />} />
      </Route>
      <Route
        path="/auctions"
        element={
          <RequireAuth>
            <div>Auctions (coming soon)</div>
          </RequireAuth>
        }
      />
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
