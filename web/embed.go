package webassets

import "embed"

// FS contains the built Vite frontend compiled into the binary.
//
//go:embed dist
var FS embed.FS

const DistDir = "dist"
