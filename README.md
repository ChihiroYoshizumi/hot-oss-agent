# Hot OSS Agent

## プロジェクトについて
Hot OSS Agent は、OSS Insight のトレンドデータと GitHub ドキュメントを収集し、 LLM エージェント（LarAgent ベース）に要約を生成させる調査支援ツールです。

## 環境構築
1. 任意のディレクトリでリポジトリを取得します。
   ```bash
   git clone git@github.com:ChihiroYoshizumi/hot-oss-agent.git
   cd hot-oss-agent
   ```
2. Node.js と PHP の依存関係をインストールします。
   ```bash
   npm install
   composer install
   ```
3. `.env.example` をコピーして `.env` を作成し、LLM や外部ツールに必要な API キー（`OPENAI_API_KEY`、`GEMINI_API_KEY`、`UPSTAGE_API_KEY`、`TAVILY_API_KEY`、`X_API_KEY` など）を入力します。
   ```bash
   cp .env.example .env
   ```
4. Laravel のアプリケーションキーを生成し、ローカル開発サーバーを起動します。
   ```bash
   php artisan key:generate
   ./vendor/bin/sail up -d 
   ```

## 使用法
```
php artisan app:oss-trending-summaries
```

### ---以下オプション---
```
{--period=past_24_hours : 集計期間 (past_24_hours|past_week|past_month|past_3_months)}
{--language=All : 対象言語：Possible values: [All, JavaScript, Java, Python, PHP, C++, C#, TypeScript, Shell, C, Ruby, Rust, Go, Kotlin, HCL, PowerShell, CMake, Groovy, PLpgSQL, TSQL, Dart, Swift, HTML, CSS, Elixir, Haskell, Solidity, Assembly, R, Scala, Julia, Lua, Clojure, Erlang, Common Lisp, Emacs Lisp, OCaml, MATLAB, Objective-C, Perl, Fortran]}
{--limit=3 : 取得するリポジトリ数 (1-'.self::MAX_LIMIT.')}'
```
