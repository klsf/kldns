package controllers

import (
	"net/http"
	"path/filepath"
	"strings"

	"github.com/beego/beego/v2/server/web"
)

type SPAController struct {
	web.Controller
}

func (c *SPAController) Index() {
	if strings.HasPrefix(c.Ctx.Request.URL.Path, "/api/") {
		c.Ctx.Output.SetStatus(http.StatusNotFound)
		return
	}
	http.ServeFile(c.Ctx.ResponseWriter, c.Ctx.Request, distFile("index.html"))
}

func (c *SPAController) Favicon() {
	http.ServeFile(c.Ctx.ResponseWriter, c.Ctx.Request, distFile("favicon.svg"))
}

func distFile(name string) string {
	root := web.WorkPath
	if root == "" {
		root = "."
	}
	return filepath.Join(root, "web", "dist", name)
}
