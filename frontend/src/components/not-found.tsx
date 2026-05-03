// used when conditions are not met to show the auctions table, e.g. when there are no auctions or when the search query does not match any auction
export function NotFound() {
  return (
    <div className="flex h-48 items-center justify-center rounded border">
      Nincs találat {":("}
    </div>
  )
}
