# 使用 mdBook 写博客

_created_at: 2023-01-16_

---

### Why

从毕业开始踏入这个行业已经有 10 年出头了，有记录的习惯，但总是断断续续，从博客平台，到 jekyll、hugo，我越来越偏爱简单。因为与我而言，博客已经不是用来呈现的地方，而是记录。

这次重新弄考虑过写 hugo 的主题或者用 gitbook、vuepress，最后选择的是 mdbook，因为 mdbook 不需要在原有的 markdown 上做太多调整，也没有太多目录、页面限制，且有扩展的口子。

### How

1. 按照文档编写文件目录：[mdBook](https://rust-lang.github.io/mdBook/index.html)
2. 使用 github actions 发布：[peaceiris/actions-mdbook](https://github.com/peaceiris/actions-mdbook)
3. 在项目的 Settings - Pages 下面选择以 branch 的方式部署，配置好域名，在域名配置出设置好 DNS 解析

注意：Summary 目录的首行不会被渲染出来，所以我在文件头添加了一个空 `#` 。

### Histroy

过往的文章都留存在这里，但不做展示了：[Github](https://github.com/xiaoler/0x1.im/blob/master/src/archives)

---

**未经过作者本人许可，本博客所有文章均不得转载。**
