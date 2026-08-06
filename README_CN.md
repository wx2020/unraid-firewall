# unraid-firewall

一个面向 Unraid 的轻量入站防火墙插件，通过 WebUI 管理 IPv4 和 IPv6 两组来源规则。

## 功能

- 全局开关：控制插件规则是否生效。
- 独立的 IPv4 / IPv6 开关。
- 每组独立设置“默认允许未匹配来源”开关。
- 支持 IP 和 CIDR，例如 `192.168.1.0/24`、`fd00::/8`。
- 每条规则可设置名称、允许/拒绝、来源 IP/CIDR、协议和目标端口/端口范围，例如 TCP `5000` 或 `5000-5010`。
- 来源可以留空表示任意来源；端口留空表示该协议的所有端口。
- 规则按输入顺序从上到下匹配。
- 使用独立的 `UNRAID_FIREWALL4` / `UNRAID_FIREWALL6` 链处理主机入站，并通过 Docker 的 `DOCKER-USER` 链挂载独立的 Docker IPv4/IPv6 链。
- Docker 规则只匹配发往 Docker bridge 接口的转发流量，保留容器出站、容器间通信和非 Docker 转发流量。
- 保存时立即应用，插件安装、数组启动和卸载时会处理规则状态。

插件过滤主机的 `INPUT` 链，以及 Docker bridge 发布端口经过的 `DOCKER-USER` 路径。host 网络容器仍属于主机 `INPUT`；macvlan/ipvlan 等非 bridge 网络不保证经过 Docker 的 `DOCKER-USER`。

## 安装

### 方法一：通过插件 URL 安装（推荐）

1. 在 Unraid 中打开 **Settings > Plugins > Install Plugin**。
2. 粘贴最新版本的插件 URL：

`https://github.com/wx2020/unraid-firewall/releases/latest/download/unraid-firewall.plg`

3. 选择 **Install**，等待安装完成。

### 方法二：手动安装

1. 打开[最新版本](https://github.com/wx2020/unraid-firewall/releases/latest)。
2. 下载 Release 附件中的 `unraid-firewall.plg`。
3. 在 Unraid 中打开 **Settings > Plugins > Install Plugin**。
4. 上传下载的 `unraid-firewall.plg` 文件，然后选择 **Install**。

上述 URL 始终安装最近发布的插件版本。合并到 `main` 的更改只有在
**Build and Release** 工作流发布新版本后，才会通过此 URL 提供。

源码中的 `plugin/unraid-firewall.plg` 是发布模板。软件包校验和会由发布
工作流替换，因此该模板本身不能作为插件安装。

## 使用

1. 在 **Settings → Network Services → Unraid Firewall** 打开 WebUI。
2. 添加规则名称、来源 IP/CIDR、协议和端口；先把管理端来源加入对应组。
3. 打开 IPv4/IPv6 组开关和全局开关。
4. 如果需要白名单模式，关闭对应的“默认允许未匹配来源”开关。
5. 点击 **Apply settings**。

插件首次安装默认关闭，且两个协议组默认允许未匹配来源，以避免安装后意外锁定管理页面。

## 语言

WebUI 使用 Unraid 标准的语言文件机制，并跟随 Unraid 中选择的语言。英文源 key 位于 `src/usr/local/emhttp/languages/en_US/unraidfirewall.txt`，插件内置简体中文翻译文件 `src/usr/local/emhttp/languages/zh_CN/unraidfirewall.txt`。

## 构建

```bash
./build.sh
# 或 Windows PowerShell
./build.ps1
```

构建会生成 `unraid-firewall-<version>-noarch-1.txz` 及 SHA256 文件。Pull Request 会通过 GitHub Actions 检查 PHP、WebUI 渲染、Shell、元数据和安装包结构。推送日期版本标签（例如 `2026.08.06.0007`，也支持 `v2026.08.06.0007`）后，流水线会自动构建安装包、计算 SHA256、生成最终 `.plg` 并创建 GitHub Release。仓库中的 `plugin/unraid-firewall.plg` 是发布模板，安装时使用 Release 中生成的 `unraid-firewall.plg`。

## 诊断

```bash
/etc/rc.d/rc.unraid-firewall status
/etc/rc.d/rc.unraid-firewall apply
tail -f /var/log/unraid-firewall.log
iptables -S UNRAID_FIREWALL4
ip6tables -S UNRAID_FIREWALL6
iptables -S DOCKER-USER
ip6tables -S DOCKER-USER
```
