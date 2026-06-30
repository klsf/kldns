package services

import (
	"context"
	"errors"
	"testing"

	"kldns/models"
	"kldns/pkg/dns"
	apperrors "kldns/pkg/errors"
	"kldns/repositories"
)

func TestRecordServiceRejectsGlobalNameConflict(t *testing.T) {
	repo := &fakeRecordRepo{
		user:         models.User{ID: 1, GroupID: 100, Status: 2, Points: 10},
		domain:       models.Domain{ID: 1, ProviderKey: "fake", RemoteZoneID: "z1", Domain: "example.com", RecordTypes: []string{"A"}},
		subdomain:    models.Subdomain{ID: 9, UID: 1, DID: 1, Name: "dd", FullDomain: "dd.example.com", Status: 1},
		nameConflict: true,
	}
	service := RecordService{Repo: repo, Resolver: fakeResolver{}}

	_, appErr := service.Submit(context.Background(), SubmitRecordInput{UserID: 1, SubdomainID: 9, Name: "www", Type: "A", Value: "1.1.1.1"})
	if appErr == nil || appErr.Code != apperrors.CodeConflict {
		t.Fatalf("error = %#v, want conflict", appErr)
	}
	if repo.applied {
		t.Fatal("record should not be applied when name conflicts")
	}
}

func TestRecordServiceCreatesNestedRecordDirectly(t *testing.T) {
	repo := &fakeRecordRepo{
		user:      models.User{ID: 1, GroupID: 100, Status: 2, Points: 10},
		domain:    models.Domain{ID: 1, ProviderKey: "fake", RemoteZoneID: "z1", Domain: "example.com", RecordTypes: []string{"A"}},
		subdomain: models.Subdomain{ID: 9, UID: 1, DID: 1, Name: "dd", FullDomain: "dd.example.com", Status: 1},
	}
	provider := &fakeProvider{}
	service := RecordService{Repo: repo, Resolver: fakeResolver{provider: provider}}

	result, appErr := service.Submit(context.Background(), SubmitRecordInput{UserID: 1, SubdomainID: 9, Name: "api.v1", Type: "A", Value: "1.1.1.1"})
	if appErr != nil {
		t.Fatal(appErr)
	}
	if result.Mode != "direct" || !provider.created || !repo.applied {
		t.Fatalf("result=%#v created=%v applied=%v", result, provider.created, repo.applied)
	}
	if repo.record.Name != "api.v1.dd" || repo.record.SubdomainID != 9 {
		t.Fatalf("unexpected record: %#v", repo.record)
	}
	if provider.lastInput.Name != "api.v1.dd" {
		t.Fatalf("provider record name = %q, want api.v1.dd", provider.lastInput.Name)
	}
}

func TestRecordServiceCreatesSubdomainApexRecordDirectly(t *testing.T) {
	repo := &fakeRecordRepo{
		user:      models.User{ID: 1, GroupID: 100, Status: 2, Points: 10},
		domain:    models.Domain{ID: 1, ProviderKey: "fake", RemoteZoneID: "z1", Domain: "klsf.cc", RecordTypes: []string{"A"}},
		subdomain: models.Subdomain{ID: 9, UID: 1, DID: 1, Name: "test", FullDomain: "test.klsf.cc", Status: 1},
	}
	provider := &fakeProvider{}
	service := RecordService{Repo: repo, Resolver: fakeResolver{provider: provider}}

	result, appErr := service.Submit(context.Background(), SubmitRecordInput{UserID: 1, SubdomainID: 9, Name: "@", Type: "A", Value: "1.1.1.1"})
	if appErr != nil {
		t.Fatal(appErr)
	}
	if result.Mode != "direct" || repo.record.Name != "test" || provider.lastInput.Name != "test" {
		t.Fatalf("result=%#v record=%#v providerInput=%#v", result, repo.record, provider.lastInput)
	}
}

func TestRecordServiceRejectsNestedRecordWhenUnlimitedDisabled(t *testing.T) {
	repo := &fakeRecordRepo{
		user:             models.User{ID: 1, GroupID: 100, Status: 2, Points: 10},
		domain:           models.Domain{ID: 1, ProviderKey: "fake", RemoteZoneID: "z1", Domain: "klsf.cc", RecordTypes: []string{"A"}},
		subdomain:        models.Subdomain{ID: 9, UID: 1, DID: 1, Name: "test", FullDomain: "test.klsf.cc", Status: 1},
		disableUnlimited: true,
	}
	provider := &fakeProvider{}
	service := RecordService{Repo: repo, Resolver: fakeResolver{provider: provider}}

	_, appErr := service.Submit(context.Background(), SubmitRecordInput{UserID: 1, SubdomainID: 9, Name: "www", Type: "A", Value: "1.1.1.1"})
	if appErr == nil || appErr.Code != apperrors.CodeForbidden {
		t.Fatalf("error = %#v, want forbidden", appErr)
	}
	if provider.created || repo.applied {
		t.Fatalf("created=%v applied=%v, want no remote write", provider.created, repo.applied)
	}

	result, appErr := service.Submit(context.Background(), SubmitRecordInput{UserID: 1, SubdomainID: 9, Name: "@", Type: "A", Value: "1.1.1.1"})
	if appErr != nil {
		t.Fatal(appErr)
	}
	if result.Mode != "direct" || repo.record.Name != "test" {
		t.Fatalf("result=%#v record=%#v", result, repo.record)
	}
}

func TestRecordServiceCompensatesRemoteCreateWhenLocalApplyFails(t *testing.T) {
	repo := &fakeRecordRepo{
		user:           models.User{ID: 1, GroupID: 100, Status: 2, Points: 10},
		domain:         models.Domain{ID: 1, ProviderKey: "fake", RemoteZoneID: "z1", Domain: "example.com", RecordTypes: []string{"A"}},
		subdomain:      models.Subdomain{ID: 9, UID: 1, DID: 1, Name: "dd", FullDomain: "dd.example.com", Status: 1},
		applyCreateErr: errors.New("local write failed"),
	}
	provider := &fakeProvider{}
	service := RecordService{Repo: repo, Resolver: fakeResolver{provider: provider}}

	_, appErr := service.Submit(context.Background(), SubmitRecordInput{UserID: 1, SubdomainID: 9, Name: "api", Type: "A", Value: "1.1.1.1"})
	if appErr == nil || appErr.Code != apperrors.CodeInternal {
		t.Fatalf("error = %#v, want internal", appErr)
	}
	if !provider.created || !provider.deleted {
		t.Fatalf("created=%v deleted=%v, want remote delete compensation", provider.created, provider.deleted)
	}
	if repo.jobCreated {
		t.Fatal("compensation job should only be written when remote delete fails")
	}
}

func TestSubdomainServiceRegistersAndChargesOnce(t *testing.T) {
	repo := &fakeRecordRepo{
		user:   models.User{ID: 1, GroupID: 100, Status: 2, Points: 10},
		domain: models.Domain{ID: 1, ProviderKey: "fake", RemoteZoneID: "z1", Domain: "example.com", RecordTypes: []string{"A"}, PointsCost: 3},
	}
	service := SubdomainService{Repo: repo}

	subdomain, appErr := service.Register(context.Background(), RegisterSubdomainInput{UserID: 1, DID: 1, Name: "dd"})
	if appErr != nil {
		t.Fatal(appErr)
	}
	if subdomain.FullDomain != "dd.example.com" || !repo.subdomainRegistered {
		t.Fatalf("subdomain=%#v registered=%v", subdomain, repo.subdomainRegistered)
	}
}

func TestSubdomainServiceDeleteRequiresFullDomainConfirmation(t *testing.T) {
	repo := &fakeRecordRepo{
		user:      models.User{ID: 1, GroupID: 100, Status: 2},
		domain:    models.Domain{ID: 1, Domain: "example.com"},
		subdomain: models.Subdomain{ID: 9, UID: 1, DID: 1, Name: "dd", FullDomain: "dd.example.com", Status: 1},
	}
	service := SubdomainService{Repo: repo}

	_, appErr := service.Delete(context.Background(), DeleteSubdomainInput{UserID: 1, ID: 9, ConfirmFullDomain: "wrong.example.com"})
	if appErr == nil || appErr.Code != apperrors.CodeInvalidArgument {
		t.Fatalf("appErr=%v, want invalid confirmation", appErr)
	}
	if repo.subdomainDeleted {
		t.Fatal("subdomain should not be deleted")
	}
}

func TestSubdomainServiceDeleteBlocksWhenRecordsExist(t *testing.T) {
	repo := &fakeRecordRepo{
		user:        models.User{ID: 1, GroupID: 100, Status: 2},
		domain:      models.Domain{ID: 1, Domain: "example.com"},
		subdomain:   models.Subdomain{ID: 9, UID: 1, DID: 1, Name: "dd", FullDomain: "dd.example.com", Status: 1},
		recordCount: 1,
	}
	service := SubdomainService{Repo: repo}

	_, appErr := service.Delete(context.Background(), DeleteSubdomainInput{UserID: 1, ID: 9, ConfirmFullDomain: "dd.example.com"})
	if appErr == nil || appErr.Code != apperrors.CodeConflict {
		t.Fatalf("appErr=%v, want conflict", appErr)
	}
	if repo.subdomainDeleted {
		t.Fatal("subdomain should not be deleted")
	}
}

func TestSubdomainServiceDeletesEmptySubdomain(t *testing.T) {
	repo := &fakeRecordRepo{
		user:      models.User{ID: 1, GroupID: 100, Status: 2},
		domain:    models.Domain{ID: 1, Domain: "example.com"},
		subdomain: models.Subdomain{ID: 9, UID: 1, DID: 1, Name: "dd", FullDomain: "dd.example.com", Status: 1},
	}
	service := SubdomainService{Repo: repo}

	result, appErr := service.Delete(context.Background(), DeleteSubdomainInput{UserID: 1, ID: 9, ConfirmFullDomain: "dd.example.com"})
	if appErr != nil {
		t.Fatal(appErr)
	}
	if !result.Deleted || !repo.subdomainDeleted {
		t.Fatalf("result=%#v subdomainDeleted=%v", result, repo.subdomainDeleted)
	}
}

func TestAdminRecordServiceCreatesDirectRecord(t *testing.T) {
	repo := &fakeRecordRepo{
		user:   models.User{ID: 1, GroupID: 100, Status: 2, Points: 10},
		domain: models.Domain{ID: 1, ProviderKey: "fake", RemoteZoneID: "z1", Domain: "example.com", RecordTypes: []string{"A"}},
	}
	provider := &fakeProvider{}
	service := AdminRecordService{Repo: repo, Resolver: fakeResolver{provider: provider}}

	result, appErr := service.Create(context.Background(), AdminRecordInput{AdminID: 99, UID: 1, DID: 1, Name: "admin", Type: "A", Value: "1.1.1.1"})
	if appErr != nil {
		t.Fatal(appErr)
	}
	if result.Mode != "direct" || !provider.created || !repo.adminCreated {
		t.Fatalf("result=%#v created=%v adminCreated=%v", result, provider.created, repo.adminCreated)
	}
}

func TestAdminDeletionServiceDeleteSubdomainDoesNotRequireTypedConfirmation(t *testing.T) {
	repo := &fakeRecordRepo{
		domain:    models.Domain{ID: 1, ProviderKey: "fake", RemoteZoneID: "z1", Domain: "example.com"},
		subdomain: models.Subdomain{ID: 9, UID: 1, DID: 1, Name: "dd", FullDomain: "dd.example.com", Status: 1},
		records:   []models.Record{{ID: 11, UID: 1, DID: 1, SubdomainID: 9, RecordID: "remote-11", Name: "dd", Type: "A", Value: "1.1.1.1"}},
	}
	provider := &fakeProvider{}
	service := AdminDeletionService{Repo: repo, Resolver: fakeResolver{provider: provider}}

	result, appErr := service.DeleteSubdomain(context.Background(), 99, 9, "admin")
	if appErr != nil {
		t.Fatal(appErr)
	}
	if !result.Deleted || provider.deleteCount != 1 || repo.deletedCount != 1 || !repo.adminSubdomainDeleted {
		t.Fatalf("result=%#v remote=%d localRecords=%d subdomainDeleted=%v", result, provider.deleteCount, repo.deletedCount, repo.adminSubdomainDeleted)
	}
}

func TestAdminDeletionServiceDeleteSubdomainDeletesRemoteRecordsFirst(t *testing.T) {
	repo := &fakeRecordRepo{
		domain:    models.Domain{ID: 1, ProviderKey: "fake", RemoteZoneID: "z1", Domain: "example.com"},
		subdomain: models.Subdomain{ID: 9, UID: 1, DID: 1, Name: "dd", FullDomain: "dd.example.com", Status: 1},
		records: []models.Record{
			{ID: 11, UID: 1, DID: 1, SubdomainID: 9, RecordID: "remote-11", Name: "dd", Type: "A", Value: "1.1.1.1"},
			{ID: 12, UID: 1, DID: 1, SubdomainID: 9, RecordID: "remote-12", Name: "www.dd", Type: "CNAME", Value: "dd.example.com"},
		},
	}
	provider := &fakeProvider{}
	service := AdminDeletionService{Repo: repo, Resolver: fakeResolver{provider: provider}}

	result, appErr := service.DeleteSubdomain(context.Background(), 99, 9, "admin")
	if appErr != nil {
		t.Fatal(appErr)
	}
	if !result.Deleted || result.RecordsDeleted != 2 || result.SubdomainsDeleted != 1 {
		t.Fatalf("result=%#v", result)
	}
	if provider.deleteCount != 2 || repo.deletedCount != 2 || !repo.adminSubdomainDeleted {
		t.Fatalf("remote=%d localRecords=%d subdomainDeleted=%v", provider.deleteCount, repo.deletedCount, repo.adminSubdomainDeleted)
	}
}

func TestAdminDeletionServiceStopsWhenRemoteDeleteFails(t *testing.T) {
	repo := &fakeRecordRepo{
		domain:    models.Domain{ID: 1, ProviderKey: "fake", RemoteZoneID: "z1", Domain: "example.com"},
		subdomain: models.Subdomain{ID: 9, UID: 1, DID: 1, Name: "dd", FullDomain: "dd.example.com", Status: 1},
		records:   []models.Record{{ID: 11, UID: 1, DID: 1, SubdomainID: 9, RecordID: "remote-11", Name: "dd", Type: "A", Value: "1.1.1.1"}},
	}
	provider := &fakeProvider{deleteErr: errors.New("remote failed")}
	service := AdminDeletionService{Repo: repo, Resolver: fakeResolver{provider: provider}}

	_, appErr := service.DeleteSubdomain(context.Background(), 99, 9, "admin")
	if appErr == nil || appErr.Code != apperrors.CodeDNSProviderFailed {
		t.Fatalf("appErr=%v, want dns provider failed", appErr)
	}
	if repo.deleted || repo.adminSubdomainDeleted {
		t.Fatalf("deleted=%v subdomainDeleted=%v, want no local delete", repo.deleted, repo.adminSubdomainDeleted)
	}
}

func TestAdminDeletionServiceDeleteUserDeletesRecordsAndSubdomains(t *testing.T) {
	repo := &fakeRecordRepo{
		user:       models.User{ID: 2, GroupID: 100, Status: 2, Username: "alice"},
		domain:     models.Domain{ID: 1, ProviderKey: "fake", RemoteZoneID: "z1", Domain: "example.com"},
		subdomains: []models.Subdomain{{ID: 9, UID: 2, DID: 1, Name: "dd", FullDomain: "dd.example.com", Status: 1}},
		records:    []models.Record{{ID: 11, UID: 2, DID: 1, SubdomainID: 9, RecordID: "remote-11", Name: "dd", Type: "A", Value: "1.1.1.1"}},
	}
	provider := &fakeProvider{}
	service := AdminDeletionService{Repo: repo, Resolver: fakeResolver{provider: provider}}

	result, appErr := service.DeleteUser(context.Background(), 99, 2, "alice", "admin")
	if appErr != nil {
		t.Fatal(appErr)
	}
	if !result.Deleted || result.RecordsDeleted != 1 || result.SubdomainsDeleted != 1 || !repo.adminUserDeleted {
		t.Fatalf("result=%#v userDeleted=%v", result, repo.adminUserDeleted)
	}
	if provider.deleteCount != 1 || repo.deletedCount != 1 || !repo.adminSubdomainDeleted {
		t.Fatalf("remote=%d localRecords=%d subdomainDeleted=%v", provider.deleteCount, repo.deletedCount, repo.adminSubdomainDeleted)
	}
}

func TestAdminDomainSyncServiceImportsRemoteRecordsForSystemUser(t *testing.T) {
	repo := &fakeRecordRepo{
		domain: models.Domain{ID: 1, ProviderKey: "fake", RemoteZoneID: "z1", Domain: "example.com"},
	}
	provider := &fakeProvider{
		records: []dns.Record{
			{RemoteID: "remote-1", Name: "WWW", Type: "a", Value: "1.1.1.1", LineID: "", Line: ""},
			{RemoteID: "", Name: "broken", Type: "A", Value: "2.2.2.2"},
		},
	}
	service := AdminDomainSyncService{Repo: repo, Resolver: fakeResolver{provider: provider}}

	result, appErr := service.SyncRecords(context.Background(), 99, 1)
	if appErr != nil {
		t.Fatal(appErr)
	}
	if result.Total != 1 || result.Created != 1 {
		t.Fatalf("sync result = %#v, want one accepted record", result)
	}
	if len(repo.syncedRecords) != 1 {
		t.Fatalf("synced records = %#v", repo.syncedRecords)
	}
	record := repo.syncedRecords[0]
	if record.Name != "www" || record.Type != "A" || record.LineID != "0" || record.Line != "默认" {
		t.Fatalf("unexpected normalized record: %#v", record)
	}
}

type fakeRecordRepo struct {
	user                  models.User
	domain                models.Domain
	subdomain             models.Subdomain
	subdomains            []models.Subdomain
	record                models.Record
	records               []models.Record
	nameConflict          bool
	applyCreateErr        error
	applied               bool
	updated               bool
	deleted               bool
	deletedCount          int
	adminCreated          bool
	adminUpdated          bool
	adminRecord           models.Record
	jobCreated            bool
	subdomainRegistered   bool
	subdomainDeleted      bool
	adminSubdomainDeleted bool
	adminUserDeleted      bool
	adminDomainDeleted    bool
	recordCount           int64
	syncedRecords         []repositories.SyncedRecordInput
	disableUnlimited      bool
}

func (r *fakeRecordRepo) GetUser(context.Context, int64) (models.User, error) {
	return r.user, nil
}

func (r *fakeRecordRepo) GetDomainForGroup(context.Context, int64, int64) (models.Domain, error) {
	return r.domain, nil
}

func (r *fakeRecordRepo) GetDomain(context.Context, int64) (models.Domain, error) {
	return r.domain, nil
}

func (r *fakeRecordRepo) GetSubdomainForUser(context.Context, int64, int64) (models.Subdomain, models.Domain, error) {
	return r.subdomain, r.domain, nil
}

func (r *fakeRecordRepo) GetSubdomain(context.Context, int64) (models.Subdomain, models.Domain, error) {
	return r.subdomain, r.domain, nil
}

func (r *fakeRecordRepo) RegisterSubdomain(context.Context, models.User, models.Domain, string, models.OperationLog) (models.Subdomain, error) {
	r.subdomainRegistered = true
	return models.Subdomain{ID: 9, UID: r.user.ID, DID: r.domain.ID, Name: "dd", FullDomain: "dd.example.com", Status: 1}, nil
}

func (r *fakeRecordRepo) CountRecordsForSubdomain(context.Context, int64, int64) (int64, error) {
	return r.recordCount, nil
}

func (r *fakeRecordRepo) DeleteSubdomain(context.Context, models.Subdomain, models.OperationLog) error {
	r.subdomainDeleted = true
	return nil
}

func (r *fakeRecordRepo) DeleteAdminSubdomain(context.Context, models.Subdomain, models.OperationLog) error {
	r.adminSubdomainDeleted = true
	return nil
}

func (r *fakeRecordRepo) DeleteAdminUser(context.Context, models.User, models.OperationLog) error {
	r.adminUserDeleted = true
	return nil
}

func (r *fakeRecordRepo) DeleteAdminDomain(context.Context, models.Domain, models.OperationLog) error {
	r.adminDomainDeleted = true
	return nil
}

func (r *fakeRecordRepo) ListRecordsForSubdomain(context.Context, int64) ([]models.Record, error) {
	return r.records, nil
}

func (r *fakeRecordRepo) ListRecordsForUser(context.Context, int64) ([]models.Record, error) {
	return r.records, nil
}

func (r *fakeRecordRepo) ListRecordsForUserCascade(context.Context, int64) ([]models.Record, error) {
	return r.records, nil
}

func (r *fakeRecordRepo) ListRecordsForDomain(context.Context, int64) ([]models.Record, error) {
	return r.records, nil
}

func (r *fakeRecordRepo) ListSubdomainsForUser(context.Context, int64) ([]models.Subdomain, error) {
	return r.subdomains, nil
}

func (r *fakeRecordRepo) ListSubdomainsForDomain(context.Context, int64) ([]models.Subdomain, error) {
	return r.subdomains, nil
}

func (r *fakeRecordRepo) GetRecordForUser(context.Context, int64, int64) (models.Record, error) {
	return r.record, nil
}

func (r *fakeRecordRepo) GetRecord(context.Context, int64) (models.Record, error) {
	return r.record, nil
}

func (r *fakeRecordRepo) RecordNameExists(context.Context, int64, string, string, int64) (bool, error) {
	return r.nameConflict, nil
}

func (r *fakeRecordRepo) AllowUnlimitedSubdomainRecords(context.Context) (bool, error) {
	if r.disableUnlimited {
		return false, nil
	}
	return true, nil
}

func (r *fakeRecordRepo) ApplyCreatedRecord(_ context.Context, _ models.User, _ models.Domain, record models.Record, _ models.OperationLog) error {
	r.applied = true
	r.record = record
	return r.applyCreateErr
}

func (r *fakeRecordRepo) ApplyUpdatedRecord(_ context.Context, _ int64, record models.Record, _ models.OperationLog) error {
	r.updated = true
	r.record = record
	return nil
}

func (r *fakeRecordRepo) ApplyDeletedRecord(context.Context, int64, models.OperationLog) error {
	r.deleted = true
	r.deletedCount++
	return nil
}

func (r *fakeRecordRepo) ApplyAdminCreatedRecord(_ context.Context, record models.Record, _ models.OperationLog) error {
	r.adminCreated = true
	r.adminRecord = record
	return nil
}

func (r *fakeRecordRepo) ApplyAdminUpdatedRecord(_ context.Context, _ int64, record models.Record, _ models.OperationLog) error {
	r.adminUpdated = true
	r.adminRecord = record
	return nil
}

func (r *fakeRecordRepo) EnqueueDNSWriteJob(context.Context, models.DNSWriteJob) error {
	r.jobCreated = true
	return nil
}

func (r *fakeRecordRepo) SyncDomainRecords(_ context.Context, _ models.Domain, records []repositories.SyncedRecordInput, _ models.OperationLog) (repositories.SyncRecordsResult, error) {
	r.syncedRecords = records
	return repositories.SyncRecordsResult{Total: len(records), Created: len(records)}, nil
}

type fakeResolver struct {
	provider *fakeProvider
}

func (r fakeResolver) Resolve(context.Context, models.Domain) (dns.Provider, error) {
	if r.provider != nil {
		return r.provider, nil
	}
	return &fakeProvider{}, nil
}

type fakeProvider struct {
	created     bool
	updated     bool
	deleted     bool
	deleteCount int
	deleteErr   error
	records     []dns.Record
	lastInput   dns.RecordInput
}

func (p *fakeProvider) Key() string                                   { return "fake" }
func (p *fakeProvider) Label() string                                 { return "Fake DNS" }
func (p *fakeProvider) ConfigFields() []dns.ConfigField               { return nil }
func (p *fakeProvider) Configure(map[string]string) error             { return nil }
func (p *fakeProvider) Check(context.Context) error                   { return nil }
func (p *fakeProvider) ListZones(context.Context) ([]dns.Zone, error) { return nil, nil }
func (p *fakeProvider) ListRecordLines(context.Context, dns.Zone) ([]dns.RecordLine, error) {
	return []dns.RecordLine{{ID: "0", Name: "默认"}}, nil
}
func (p *fakeProvider) CreateRecord(_ context.Context, _ dns.Zone, input dns.RecordInput) (dns.Record, error) {
	p.created = true
	p.lastInput = input
	return dns.Record{RemoteID: "remote-1", LineID: "0", Line: "默认"}, nil
}
func (p *fakeProvider) UpdateRecord(_ context.Context, _ dns.Zone, _ string, input dns.RecordInput) (dns.Record, error) {
	p.updated = true
	p.lastInput = input
	return dns.Record{RemoteID: "remote-1", LineID: "0", Line: "默认"}, nil
}
func (p *fakeProvider) DeleteRecord(context.Context, dns.Zone, string) error {
	p.deleted = true
	p.deleteCount++
	if p.deleteErr != nil {
		return p.deleteErr
	}
	return nil
}
func (p *fakeProvider) GetRecord(context.Context, dns.Zone, string) (dns.Record, error) {
	return dns.Record{}, nil
}
func (p *fakeProvider) ListRecords(context.Context, dns.Zone) ([]dns.Record, error) {
	return p.records, nil
}
