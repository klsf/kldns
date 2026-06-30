package dns

type ProviderError struct {
	Provider  string
	Operation string
	Message   string
	Cause     error
}

func (e *ProviderError) Error() string {
	if e == nil {
		return ""
	}
	if e.Cause == nil {
		return e.Provider + " " + e.Operation + ": " + e.Message
	}
	return e.Provider + " " + e.Operation + ": " + e.Message + ": " + e.Cause.Error()
}
