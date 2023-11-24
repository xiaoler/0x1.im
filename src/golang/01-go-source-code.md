# 从源码看 go

_created_at: 2023-11-25_

---

go 的源码库本身是一个很好的 go 代码的样例库，如果要对 go 理解更深刻，可以多看。

### 如何编译或索引代码

从 Github 或者官网下载一份代码，进入到 src 目录执行 `make.bash` 或者 `make.bat` 即可，这个前期是本地的有一个在 PATH 下能被找到的 go 版本。因为 go 的编译是自举的。

如果用 ide 直接打开 go 项目目录，ide 可能会报错，因为系统本身已经有一个 go 环境了，这时候跳函数也会跳到已安装的 go 版本上。解决这个问题的方法很简单，将环境变量中的 `GOPATH` 设置为当前目录（绝对路径），同时 GOPATH 下的 `bin` 目录加入 `PATH`（要在已经安装版本之前），重装 gopls，即可正确索引代码。

### go 源码组成

简单来说，go 源码主要组成可以分为三部分（src 下，src 目录外的不重要）：

-   供 go 程序引用的 `std` 包，也就是 src 目录下除了 cmd、internal 和 builtin 目录的全部部分；
-   go 编译器部分，也就是用来编译其他 go 程序的工具链，在 cmd/compile 和 cmd/go 目录下；
-   go 用来自举的工具链（也就是编译 go 编译器自身），主要在 cmd/dist 目录下，这个从 `make.bash` 里可以看出来；

当然这三部分并不是完全相互独立的，会有共同依赖的部分；甚至有个很特别的地方：`src/cmd/compile/internal/types2` 整个就是 copy 的 `src/go/types` 目录，既是对外暴漏的 std 包，也是编译器需要的依赖。

所以再阅读 go 代码时，可以根据我们的目的来选择，如果是要看写代码需要引用的依赖，看 std 部分就行，如果是要分析编译过程，就看编译器部分，而如果想理解 go 自举的过程，就看自举部分。

但是这里面又有个需要特别注意的点：go 的内置函数并不是在 std 包中实现的，在 builtin 目录下只能看到对应的定义，内置函数真正的实现在编译器部分，`src/cmd/compile/internal/types2/builtins.go` 文件里。

### 玩两个把戏

既然知道了 go 目录结构的分工，我们就来玩两个把戏吧：

1. 我们知道 go 1.21 版本史诗级的加入了 min/max 函数，现在我们把这两个函数的作用调换过来，也就是说 min 的表现是 max，max 的表现是 min；
2. cmp.Compare 同样是用来比较大小的，根据大小返回 -1、0 或者 1，我们同样把结果调换过来；

玩这两个把戏不是出于好玩的目的，而是为了验证本地的 go 源码配置的编译和 std 包部分都是走的本地这份源码。

打开 `src/cmd/compile/internal/types2/builtins.go` 文件，搜索 `case _Max, _Min:`，下面就是关于 min/max 函数的实现了，能看到以下代码：

```go
		op := token.LSS
		if id == _Max {
			op = token.GTR
		}
```

我们稍作修改，把 op 调换一下位置：

```go
		op := token.GTR
		if id == _Max {
			op = token.LSS
		}
```

打开 `src/cmp/cmp.go`，直接修改 `Compare` 函数，找到以下代码：

```go
	if xNaN || x < y {
		return -1
	}
	if yNaN || x > y {
		return +1
	}
```

我们调整一下 `+1` 和 `-1` 的位置，修改为：

```go
	if xNaN || x < y {
		return +1
	}
	if yNaN || x > y {
		return -1
	}
```

接下来执行 `make.bash` 进行编译。

编译完成后，写一个测试文件命名为 `main_test.go`，内容如下：

```go
package main

import (
	"cmp"
	"fmt"
	"testing"
)

func TestExample1(t *testing.T) {
	fmt.Println(min(1, 2))
	fmt.Println(max(3, 4))
}

func TestExample2(t *testing.T) {
	fmt.Println(cmp.Compare(1, 2))
	fmt.Println(cmp.Compare(4, 3))
}
```

进入 `bin` 目录，执行 `go test -v ../main_test.go`，结果如下：

```
=== RUN   TestExample1
2
3
--- PASS: TestExample1 (0.00s)
=== RUN   TestExample2
1
-1
--- PASS: TestExample2 (0.00s)
PASS
ok      command-line-arguments  0.382s
```

比较和正常的 go 程序的结果相反，目的达成。事后不要忘了恢复刚才修改过的两个文件。
