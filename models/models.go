package models

type User struct {
	ID       int64  `json:"id"`
	GroupID  int64  `json:"group_id"`
	Status   int    `json:"status"`
	Username string `json:"username"`
	Email    string `json:"email"`
	Points   int64  `json:"points"`
}

type Domain struct {
	ID                       int64    `json:"id"`
	ProviderKey              string   `json:"provider_key"`
	ProviderConfigCiphertext string   `json:"-"`
	RemoteZoneID             string   `json:"remote_zone_id"`
	Domain                   string   `json:"domain"`
	GroupPolicy              string   `json:"group_policy"`
	RecordTypes              []string `json:"record_types"`
	Beian                    int      `json:"beian"`
	PointsCost               int64    `json:"points_cost"`
	Description              string   `json:"description"`
}

type Record struct {
	ID          int64  `json:"id"`
	UID         int64  `json:"uid"`
	DID         int64  `json:"did"`
	SubdomainID int64  `json:"subdomain_id"`
	RecordID    string `json:"record_id"`
	Name        string `json:"name"`
	Type        string `json:"type"`
	Value       string `json:"value"`
	LineID      string `json:"line_id"`
	Line        string `json:"line"`
}

type Subdomain struct {
	ID         int64  `json:"id"`
	UID        int64  `json:"uid"`
	DID        int64  `json:"did"`
	Name       string `json:"name"`
	FullDomain string `json:"full_domain"`
	Status     int    `json:"status"`
	CreatedAt  int64  `json:"created_at"`
	UpdatedAt  int64  `json:"updated_at"`
}

const (
	SubdomainStatusActive   = 1
	SubdomainStatusDisabled = 0
)

type OperationLog struct {
	UID        int64
	AdminUID   int64
	Source     string
	TargetType string
	TargetID   string
	IP         string
	Action     string
	Message    string
	Extra      string
}

type DNSWriteJob struct {
	UID            int64
	Source         string
	ProviderKey    string
	Domain         string
	RecordName     string
	RecordType     string
	ValueDigest    string
	RemoteRecordID string
	Operation      string
	Status         string
	LastError      string
	Payload        string
}
