import { StrictMode } from "react"
import { createRoot } from "react-dom/client"

import "./index.css"
import App from "./App.tsx"
import { ThemeProvider } from "@/components/theme-provider.tsx"
import { BrowserRouter } from "react-router-dom"
import { Toaster } from "./components/ui/sonner.tsx"

Intl.NumberFormat("hu-HU", { style: "currency", currency: "HUF" })

createRoot(document.getElementById("root")!).render(
  <StrictMode>
    <BrowserRouter>
      <ThemeProvider>
        <Toaster richColors={true} position="top-center" />
        <App />
      </ThemeProvider>
    </BrowserRouter>
  </StrictMode>
)
