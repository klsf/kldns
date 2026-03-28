UPDATE `kldns_configs`
SET `v` = '{"name":"KLDNS","title":"KLDNS - 二级域名分发与解析管理平台","keywords":"KLDNS,二级域名分发,DNS解析,域名管理平台","description":"KLDNS 用于二级域名分发、DNS 解析管理、用户自助申请与后台统一运维。"}'
WHERE `k` = 'array_web';

UPDATE `kldns_configs`
SET `v` = '<div class="alert alert-primary">\r\n本站提供二级域名分发与解析服务，适用于测试、学习与内部业务接入。请遵守相关法律法规与平台使用规范。\r\n</div>'
WHERE `k` = 'html_header';

UPDATE `kldns_configs`
SET `v` = '欢迎使用 KLDNS 用户控制台。添加解析前请确认主机记录、记录类型与记录值填写正确，并遵守平台解析规范。'
WHERE `k` = 'html_home';

UPDATE `kldns_configs`
SET `v` = '源码下载|https://github.com/klsf/kldns'
WHERE `k` = 'index_urls';
