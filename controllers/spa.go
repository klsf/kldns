package controllers

import (
	"io/fs"
	"net/http"
	"os"
	"path"
	"path/filepath"
	"strings"

	"github.com/beego/beego/v2/server/web"
)

type SPAController struct {
	web.Controller
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
		c.Ctx.Output.SetStatus(http.StatusNotFound)
		return
	}
	serveFrontendFile(c, "index.html")
}

func (c *SPAController) Favicon() {
	serveFrontendFile(c, "favicon.svg")
}

func (c *SPAController) Asset() {
	name := strings.TrimPrefix(c.Ctx.Request.URL.Path, "/assets/")
	if name == "" || strings.Contains(name, "..") {
		c.Ctx.Output.SetStatus(http.StatusNotFound)
		return
	}
	serveFrontendFile(c, path.Join("assets", name))
}

func serveFrontendFile(c *SPAController, name string) {
	http.ServeFileFS(c.Ctx.ResponseWriter, c.Ctx.Request, currentFrontendFS(), name)
}

func currentFrontendFS() fs.FS {
	if frontendFS != nil {
		return frontendFS
	}
	return os.DirFS(distRoot())
}

func distRoot() string {
	root := web.WorkPath
	if root == "" {
		root = "."
	}
	return filepath.Join(root, "web", "dist")
}
