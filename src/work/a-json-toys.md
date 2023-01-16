# 使用 Golang 实现一个 JSON 命令行工具

_created_at: 2020-07-08_

---

首先先提一个问题，`"abc"` 、`123` 或者 `[1, 2, 3]` 是不是一个合法的 json ？

之前一直有在使用一个 json 的命令行工具 [jq](https://github.com/stedolan/jq)，这个工具是基于 flex 和 bison 来实现的（去了解这些是基于当年学习 php 的经历）。后来有段时间我又发现一个不错的词法和语法分析工具 [antlr](https://github.com/antlr/antlr4)，它支持多种语言的生成，并且本身也提供了多种语言的基本语法文件。所以我就想能不用基于它实现一个 go 语言版的 json 命令行工具。

下面就开始一步一步行动吧（如果想直接看代码可以直接拉到底部），我将这个项目命名为 `jtlr`。

### 提供的功能

根据我自己常使用的场景，我要实现以下几个功能：

基本用法：

> jtlr '{"a": 1}'

交互模式，可以多次输入，并且最好能支持上下切换：

> jtlr -a

从标准输入中读取内容，可以格式化实时输出的日志：

> tail -f xxx.log \| jtlr -s

从文件中读取：

> jtlr -f xxx.log

### 什么是 json

在动手之前，先要对 [json](https://www.json.org/) 有一个全面的认识。先来大致看一下官网提供的 json 的 BNF 范式的起始部分：

```
json
    element

value
    object
    array
    string
    number
    "true"
    "false"
    "null"

...

element
    ws value ws
```

`ws` 是 whitespace 的缩写，即空白字符，忽略这个之后，即可简单清晰的看到 json 的内的有效数值。虽然我们常用的 json 内容都是 object 起的，但并不是一定要从 object 开始，所以对于文章开头那个问题，你有答案了吗？

在实现时我并没有去复制官网提供的 BNF，而是采用了 antlr4 提供的语法，关于它的实现，这里有一篇文章说明：[https://andreabergia.com/a-grammar-for-json-with-antlr-v4/](https://andreabergia.com/a-grammar-for-json-with-antlr-v4/) 。

简单来说，json 有七种的数据，其中 `array` 和 `object` 是可以再包含 `value`，剩下五种就是基本的数值数据。

此外，还有一类比较特殊的情况，就是对 `string` 的用法：

```
member
    ws string ws ':' element
```

`string` 既可以是一个基本类型的 `value`，也可以一个对象成员的键值。这会导致我们在对 `string` 做上色等处理时需要考虑着两种情况。

### antlr4 提供的接口

使用下面的命令即可生成基于 go 语言的 lexer 和 parser：

> antlr -Dlanguage=Go -o parser/ JSON.g4

接下来就是功能实现的工作了。

antlr4 生成的接口比较完备，包含每个分支逻辑进入、退出和错误节点访问的接口。并且有较好的错误纠正和提示机制。

但对于 json 本身这个 case，需要注意的是对 `value` 和 `string`。上面也有提到，所有七类数值都是 `value`，所以都会触发 `EnterValue` 和 `ExitValue` 事件，`string` 同理。

对于 `object` 和 `array` 来说，比较棘手的在于嵌套的数据，例如：

> {"a": [134, {"a": 1}, true, [1, 2, 3], false]}

在使用 antlr4 提供的接口时，需要标注进入和退出的顺序。

### 交互模式下的问题

最开始我做了个非常简单的交互模式的实现：

```go
reader := bufio.NewReader(os.Stdin)
for {
    fmt.Print(">>> ")
    text, err := reader.ReadString('\n')
    if err == io.EOF {
        break
    }
    if text == "\n" || text == "\r\n" {
        continue
    }
    fn(text)
}
```

但是在这种实现逻辑下，上下左右等按键会直接打印在屏幕上而无法正确处理，因为终端处于 **cooked mode** 下。go 本身也没有提供 tty 的封装。所以要进入 **raw mode**，一种是通过直接 call 起命令行的方式：

```go
func raw(start bool) error {
    r := "raw"
    if !start {
        r = "-raw"
    }

    rawMode := exec.Command("stty", r)
    rawMode.Stdin = os.Stdin
    err := rawMode.Run()
    if err != nil {
        return err
    }

    return rawMode.Wait()
}
```

另外一种是操作 stdin 的文件句柄，这样实现起来就相当复杂了。

出于兼容性和可维护性的考虑，我使用了 [golang/crypto](https://github.com/golang/crypto) 提供的 terminal 的封装，这也是项目中除了 antlr 以外唯一一个引入的第三方包（如果算是第三方的话）。

但是这个包有一个问题是必须使用 `\r\n` 进行回车（官方的 issue 解释是一些历史原因吧啦吧啦），不然光标不会回到行首，但是 go 标准的 fmt 包中使用的 `\n` 换行，而 antlr 使用了 fmt 进行错误输出，所以需要对错误输出进行重载。

### 未完成

从开始构思到实现到当前阶段，大概耗时两个周末了。

由于前期偷懒，格式化输出全部使用的是 fmt，这里后续需要优化一下。

现在的实现对于 antlr 来说有点像用牛刀杀鸡，jq 本身支持的节点选取，这是后续实现的一个方向。

另外，go 官方虽然提供了官方的 json 序列化和反序列化工具，但是市面上也有一些第三方的实现被使用，我也想探讨一下实现方式。

另外，windows 下还没做完全的兼容测试。

最后，贴上项目地址：[https://github.com/XiaoLer/jtlr-go](https://github.com/XiaoLer/jtlr-go)
