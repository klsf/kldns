export const RECORD_TYPES = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA']

const RECORD_TYPE_USAGE: Record<string, string> = {
  A: '指向 IPv4 地址',
  AAAA: '指向 IPv6 地址',
  CNAME: '指向另一个域名',
  MX: '设置邮件服务器',
  TXT: '验证或声明文本',
  NS: '指定权威 DNS',
  SRV: '服务发现记录',
  CAA: '限制证书签发',
}

export function recordTypeUsage(type: string) {
  return RECORD_TYPE_USAGE[type.toUpperCase()] || '自定义解析类型'
}
