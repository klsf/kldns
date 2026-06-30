package controllers

import (
	"io/fs"
	"net/http"
	"os"
	"path"
	"path/filepath"
	"strings"

	"github.com/gin-gonic/gin"
)

type SPAController struct {
	Ctx *gin.Context
}

func (c *SPAController) SetContext(ctx *gin.Context) {
	c.Ctx = ctx
}

var frontendFS fs.FS

func SetFrontendFS(source fs.FS, root string) error {
	sub, err := fs.Sub(source, root)
	if err != nil {
		return err
	}
	frontendFS = sub
	return nil
}

func (c *SPAController) Index() {
	if strings.HasPrefix(c.Ctx.Request.URL.Path, "/api/") {
		c.Ctx.Status(http.StatusNotFound)
		return
	}
	if c.Ctx.Request.Method != http.MethodGet {
		c.Ctx.Status(http.StatusNotFound)
		return
	}
	serveFrontendFile(c, "index.html")
}

func (c *SPAController) Favicon() {
	serveFrontendFile(c, "favicon.svg")
}

func (c *SPAController) Asset() {
	name := strings.TrimPrefix(c.Ctx.Param("filepath"), "/")
	if name == "" || strings.Contains(name, "..") {
		c.Ctx.Status(http.StatusNotFound)
		return
	}
	serveFrontendFile(c, path.Join("assets", name))
}

func serveFrontendFile(c *SPAController, name string) {
	http.ServeFileFS(c.Ctx.Writer, c.Ctx.Request, currentFrontendFS(), name)
}

func currentFrontendFS() fs.FS {
	if frontendFS != nil {
		return frontendFS
	}
	return os.DirFS(distRoot())
}

func distRoot() string {
	root, err := os.Getwd()
	if err != nil || root == "" {
		root = "."
	}
	return filepath.Join(root, "web", "dist")
}
