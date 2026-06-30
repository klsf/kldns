package dns

import (
	"context"
	"sort"
	"sync"
)

type Provider interface {
	Key() string
	Label() string
	ConfigFields() []ConfigField
	Configure(config map[string]string) error
	Check(ctx context.Context) error
	ZoneManager
	RecordManager
}

type ZoneManager interface {
	ListZones(ctx context.Context) ([]Zone, error)
	ListRecordLines(ctx context.Context, zone Zone) ([]RecordLine, error)
}

type RecordManager interface {
	CreateRecord(ctx context.Context, zone Zone, input RecordInput) (Record, error)
	UpdateRecord(ctx context.Context, zone Zone, remoteID string, input RecordInput) (Record, error)
	DeleteRecord(ctx context.Context, zone Zone, remoteID string) error
	GetRecord(ctx context.Context, zone Zone, remoteID string) (Record, error)
	ListRecords(ctx context.Context, zone Zone) ([]Record, error)
}

type ConfigField struct {
	Name        string `json:"name"`
	Label       string `json:"label"`
	Required    bool   `json:"required"`
	Secret      bool   `json:"secret"`
	Description string `json:"description,omitempty"`
}

type Zone struct {
	ID     string `json:"id"`
	Domain string `json:"domain"`
}

type RecordLine struct {
	ID   string `json:"id"`
	Name string `json:"name"`
}

type RecordInput struct {
	Name   string `json:"name"`
	Type   string `json:"type"`
	Value  string `json:"value"`
	LineID string `json:"line_id"`
}

type Record struct {
	RemoteID string `json:"remote_id"`
	Name     string `json:"name"`
	Type     string `json:"type"`
	Value    string `json:"value"`
	LineID   string `json:"line_id"`
	Line     string `json:"line"`
}

type Factory func() Provider

var registry = struct {
	sync.RWMutex
	items map[string]Factory
}{items: map[string]Factory{}}

func Register(key string, factory Factory) {
	registry.Lock()
	defer registry.Unlock()
	registry.items[key] = factory
}

func New(key string) (Provider, bool) {
	registry.RLock()
	defer registry.RUnlock()
	factory, ok := registry.items[key]
	if !ok {
		return nil, false
	}
	return factory(), true
}

func RegisteredKeys() []string {
	registry.RLock()
	defer registry.RUnlock()
	keys := make([]string, 0, len(registry.items))
	for key := range registry.items {
		keys = append(keys, key)
	}
	sort.Strings(keys)
	return keys
}
