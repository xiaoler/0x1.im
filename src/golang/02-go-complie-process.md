# Go 编译过程

一个普通的、直接在操作系统上运行的的编译型语言，其实编译流程本身都差不多。

go 的编译过程，按照[官方文档](https://sourcegraph.com/github.com/golang/go/-/blob/src/cmd/compile/README.md)的说法，分为以下几步：

1. Parsing
2. Type checking
3. IR construction ("noding")
4. Middle end
5. Walk
6. Generic SSA
7. Generating machine code

但其实这种说法还是比较笼统的，每一步又包含多个子动作。

上面提到的文档中有说到，在编译器代码里，"gc" 代表的是 "Go compiler"（go 编译器）, 大写的 "GC" 才表示 "garbage collection"（垃圾回收），不要混淆。

## 1. 入口
