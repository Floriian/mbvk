import { clsx, type ClassValue } from "clsx"
import { twMerge } from "tailwind-merge"

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export function formatDate(dateString: string) {
  const date = new Date(dateString)
  return date.toLocaleDateString("hu-HU", {
    year: "numeric",
    month: "long",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  })
}

export function formatPrice(price: unknown) {
  if (typeof price !== "number") throw new Error("Price must be a number")
  return new Intl.NumberFormat("hu-HU", {
    style: "currency",
    currency: "HUF",
  }).format(price)
}
