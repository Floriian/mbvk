FROM golang:1.22-alpine AS build
RUN apk add --no-cache git
WORKDIR /src
COPY go.mod ./
RUN go mod download || true
COPY . .
RUN go mod tidy && CGO_ENABLED=0 go build -o /out/legacy-go ./...

FROM alpine:3.20
COPY --from=build /out/legacy-go /usr/local/bin/legacy-go
EXPOSE 8080
CMD ["/usr/local/bin/legacy-go"]