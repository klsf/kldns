package validation

import (
	"net"
	"regexp"
	"strconv"
	"strings"
	"unicode/utf8"
)

var (
	hostLabelPattern = regexp.MustCompile(`^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$`)
	prefixPattern    = regexp.MustCompile(`^[a-z0-9_-]+$`)
	srvPattern       = regexp.MustCompile(`^\s*(\d{1,5})\s+(\d{1,5})\s+(\d{1,5})\s+(.+)\s*$`)
	caaPattern       = regexp.MustCompile(`^\s*(\d{1,3})\s+([A-Za-z0-9-]+)\s+(.+)\s*$`)
	emailPattern     = regexp.MustCompile(`^[a-zA-Z0-9._-]+@([a-zA-Z0-9_-]+\.)+[a-zA-Z]+$`)
)

var RecordValueTips = map[string]string{
	"A":     "请输入 IPv4 地址，例如 1.1.1.1",
	"AAAA":  "请输入 IPv6 地址，例如 2400:3200::1",
	"CNAME": "请输入目标域名，例如 target.example.com",
	"MX":    "请输入邮件服务器域名，例如 mail.example.com",
	"TXT":   "请输入文本内容，例如 v=spf1 include:_spf.google.com ~all",
	"NS":    "请输入权威 DNS 服务器域名，例如 ns1.example.com",
	"SRV":   "请输入 priority weight port target，例如 10 5 443 srv.example.com",
	"CAA":   "请输入 flags tag value，例如 0 issue letsencrypt.org",
}

func NormalizeRecordTypes(types []string) []string {
	allowed := map[string]bool{
		"A": true, "AAAA": true, "CNAME": true, "MX": true,
		"TXT": true, "NS": true, "SRV": true, "CAA": true,
	}
	seen := make(map[string]bool)
	out := make([]string, 0, len(types))
	for _, typ := range types {
		typ = strings.ToUpper(strings.TrimSpace(typ))
		if typ == "" || !allowed[typ] || seen[typ] {
			continue
		}
		seen[typ] = true
		out = append(out, typ)
	}
	if len(out) == 0 {
		return []string{"A", "CNAME"}
	}
	return out
}

func ValidateRecordPrefix(name string, reserved []string) (string, string, bool) {
	name = strings.ToLower(strings.TrimSpace(name))
	if name == "" {
		return "", "请输入域名前缀", false
	}
	for _, item := range reserved {
		if name == strings.ToLower(strings.TrimSpace(item)) {
			return "", "对不起，此前缀暂不对外开放", false
		}
	}
	if name == "@" {
		return name, "", true
	}
	if !prefixPattern.MatchString(name) {
		return "", "域名前缀格式不正确", false
	}
	return name, "", true
}

func ValidateSubdomainLabel(name string, reserved []string) (string, string, bool) {
	name = strings.ToLower(strings.TrimSpace(name))
	if name == "" {
		return "", "请输入二级域名前缀", false
	}
	if name == "@" || strings.Contains(name, ".") {
		return "", "二级域名前缀只能填写单段名称", false
	}
	for _, item := range reserved {
		if name == strings.ToLower(strings.TrimSpace(item)) {
			return "", "对不起，此前缀暂不对外开放", false
		}
	}
	if !hostLabelPattern.MatchString(name) {
		return "", "二级域名前缀格式不正确", false
	}
	return name, "", true
}

func ValidateRelativeRecordName(name string) (string, string, bool) {
	name = strings.ToLower(strings.TrimSpace(name))
	if name == "" {
		return "", "请输入主机记录", false
	}
	if name == "@" {
		return name, "", true
	}
	parts := strings.Split(name, ".")
	for _, part := range parts {
		if !hostLabelPattern.MatchString(part) {
			return "", "主机记录格式不正确", false
		}
	}
	return name, "", true
}

func ValidateRecordValue(recordType string, value string) (string, bool) {
	recordType = strings.ToUpper(strings.TrimSpace(recordType))
	value = strings.TrimSpace(value)
	if value == "" {
		return "请输入记录值", false
	}

	switch recordType {
	case "A":
		if ip := net.ParseIP(value); ip == nil || ip.To4() == nil {
			return RecordValueTips["A"], false
		}
	case "AAAA":
		if ip := net.ParseIP(value); ip == nil || ip.To4() != nil || ip.To16() == nil {
			return RecordValueTips["AAAA"], false
		}
	case "CNAME", "MX", "NS":
		if !IsValidDNSTarget(value) {
			return RecordValueTips[recordType], false
		}
	case "TXT":
		if utf8.RuneCountInString(value) > 1024 {
			return "TXT 记录值过长，请控制在 1024 个字符内", false
		}
	case "SRV":
		matches := srvPattern.FindStringSubmatch(value)
		if matches == nil {
			return RecordValueTips["SRV"], false
		}
		priority, _ := strconv.Atoi(matches[1])
		weight, _ := strconv.Atoi(matches[2])
		port, _ := strconv.Atoi(matches[3])
		if priority > 65535 || weight > 65535 || port < 1 || port > 65535 || !IsValidDNSTarget(matches[4]) {
			return RecordValueTips["SRV"], false
		}
	case "CAA":
		matches := caaPattern.FindStringSubmatch(value)
		if matches == nil {
			return RecordValueTips["CAA"], false
		}
		flags, _ := strconv.Atoi(matches[1])
		tag := strings.ToLower(matches[2])
		tagValue := strings.Trim(matches[3], " \t\n\r\x00\x0B\"'")
		if flags < 0 || flags > 255 || tagValue == "" || (tag != "issue" && tag != "issuewild" && tag != "iodef") {
			return RecordValueTips["CAA"], false
		}
	}
	return "", true
}

func IsValidDNSTarget(value string) bool {
	value = strings.ToLower(strings.TrimSuffix(strings.TrimSpace(value), "."))
	if value == "" || len(value) > 253 || net.ParseIP(value) != nil {
		return false
	}
	labels := strings.Split(value, ".")
	if len(labels) < 2 {
		return false
	}
	tld := labels[len(labels)-1]
	if len(tld) < 2 || len(tld) > 63 {
		return false
	}
	for _, label := range labels {
		if !hostLabelPattern.MatchString(label) {
			return false
		}
	}
	return true
}

func IsValidEmail(email string) bool {
	return emailPattern.MatchString(strings.TrimSpace(email))
}
