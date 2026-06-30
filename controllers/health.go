package controllers

type HealthController struct {
	APIController
}

func (c *HealthController) Get() {
	c.OK(map[string]any{"service": "kldns"})
}
