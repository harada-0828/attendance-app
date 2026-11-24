# COACHTECH 勤怠管理アプリ (attendance-app)

## 概要
本アプリケーションは、PHP 8.5 / Laravel 10.x および Laravel Sail（MySQL 8.4 / Mailpit / phpMyAdmin）環境を用いた勤怠管理システムです。
一般ユーザー向けの会員登録・ログイン・勤怠打刻（出勤・休憩入/戻・退勤）・勤怠一覧/詳細・修正申請および、管理者向けの日次勤怠一覧・スタッフ別月次勤怠一覧・修正申請承認などの基本機能に加え、応用機能としてメール認証、マイ勤怠レポート（`/attendance/report`）、Sanctum認証・Policy認可付きの公開API（`/api/v1/attendance-records`）を備えています。

開発プロセスシートの要件（型宣言、PHPDoc、Collectionメソッドの活用、Eager LoadingによるN+1対策、指定技術スタックの遵守など）に沿って実装し、PHPUnitによるテストスイートを用意しています。

## ER図
![ER図](ER.drawio.png)

## 環境構築手順

### 1. リポジトリのクローンと移動
リポジトリをローカル環境にクローンし、プロジェクトディレクトリへ移動します。

* **クローンコマンド**: `git clone https://github.com/harada-0828/attendance-app.git`
* **移動コマンド**: `cd attendance-app`

### 2. 環境変数の設定
`.env.example` をコピーして `.env` ファイルを作成し、データベース接続情報およびメール送信先（Mailpit）の設定を確認します。

* **作成コマンド**: `cp .env.example .env`

* **データベース設定**: 
`DB_CONNECTION=mysql` 
`DB_HOST=mysql` 
`DB_PORT=3306` 
`DB_DATABASE=laravel` 
`DB_USERNAME=sail` 
`DB_PASSWORD=password`

* **メール設定（Mailpit）**: 
`MAIL_MAILER=smtp` 
`MAIL_HOST=mailpit` 
`MAIL_PORT=1025`


* **注意点**: `DB_HOST` は `127.0.0.1` ではなく Docker コンテナ名の `mysql` を指定してください。共有した `.env.example` の初期値は `DB_USERNAME=root`・`DB_PASSWORD=` となっているため、Sail 接続に合わせて `DB_USERNAME=sail`・`DB_PASSWORD=password` に変更してください。また、Apple Silicon（M1/M2/M3/M4 Mac 等）をお使いの場合は、`compose.yaml` の `mysql` サービスに `platform: 'linux/amd64'` が設定されていること、および `mysql`、`mailpit`、`phpmyadmin` の各サービス定義が含まれていることを確認してください。




### 3. Composerパッケージのインストール
Laravel Sail の PHP 8.5 イメージを利用して依存パッケージをインストールします。


* **実行コマンド**: `docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html -e COMPOSER_CACHE_DIR=/tmp/composer_cache laravelsail/php82-composer:latest composer install --ignore-platform-reqs`

### 4. Laravel Sail の起動とエイリアス設定
Docker コンテナをバックグラウンドで起動し、必要に応じて `sail` エイリアスを設定します。

* **起動コマンド**: `./vendor/bin/sail up -d`

* **エイリアス設定（Zsh の場合・任意）**: `echo "alias sail='[ -f sail ] && bash sail || bash vendor/bin/sail'" >> ~/.zshrc && exec $SHELL`

### 5. アプリケーションキーの生成
Laravel の暗号化キーを生成します。

* **実行コマンド**: `sail artisan key:generate`

### 6. データベースのマイグレーションと初期データ投入
テーブル作成とシーダーによる初期データの投入を行います。

* **実行コマンド**: `sail artisan migrate --seed`
* **リセット再投入**: `sail artisan migrate:fresh --seed`

### 7. フロントエンドの依存関係インストールとビルド
Vite によるアセットのインストールと開発サーバー起動を行います。

* **インストールコマンド**: `sail npm install`
* **起動コマンド**: `sail npm run dev`
* **注意点**: `sail npm run dev` は開発サーバーを起動したままにしておく必要があります。

### 8. テストの実行
PHPUnit テストスイートを実行して動作を検証します。

* **実行コマンド**: `sail artisan test`

### テスト構成・カバレッジ実績
実装した機能の動作確認および回帰テストのため、各種テストケースを用意しています。

* **テスト結果**: 73 passed（244 assertions）
* **コードカバレッジ**: 82.8%
* **基本要件テスト（Id01〜Id15）**: 会員登録・一般/管理者ログイン・打刻（出勤/休憩/退勤）・勤怠一覧/詳細/修正申請・管理者一覧/詳細修正/スタッフ一覧/申請承認
* **応用要件テスト（Id16〜Id20）**: メール認証・公開REST API（V1 読み取り/書き込み）・Sanctum認証・マイ勤怠レポート
* **独自追加テスト**: 管理者向けCSV出力テスト（`AdminAttendanceCsvExportTest`）など、仕様要件に加えて独自に検証ケースを追加
* **セキュリティ・日本語化方針**: サプライチェーン攻撃対策のため、指定技術スタック以外の外部 Composer パッケージ（`laravel-lang/*` 等）は追加せず、日本語化メッセージは `lang/` ディレクトリ配下で管理しています。

## シーディング仕様・テスト用ユーザ
`sail artisan migrate --seed` を実行することで、以下のテスト用ユーザおよび勤怠・休憩などのダミーデータが作成されます。

### 1. テスト用ユーザ (UserSeeder)
管理画面へのログインおよびフロントエンド認証に使用します。

* **一般ユーザー1**
  * **ユーザ名**: 一般ユーザー1
  * **メールアドレス**: `user1@example.com`
  * **パスワード**: `password`
  * **状態**: メール認証済み / `admin_status = false`
* **一般ユーザー2**
  * **ユーザ名**: 一般ユーザー2
  * **メールアドレス**: `user2@example.com`
  * **パスワード**: `password`
  * **状態**: メール認証済み / `admin_status = false`
* **管理者ユーザー3**
  * **ユーザ名**: 管理者ユーザー3
  * **メールアドレス**: `user3@example.com`
  * **パスワード**: `password`
  * **状態**: メール認証済み / `admin_status = true`

### 2. 初期データ・ダミーデータ構成 (DatabaseSeeder)
以下の順番で各種シーダーが実行されます。

* **UserSeeder（`users` テーブル）**
  * **登録件数**: 固定3件
  * **登録内容**: `user1@example.com`、`user2@example.com`（一般ユーザー / `admin_status = false`）、`user3@example.com`（管理者ユーザー / `admin_status = true`）を登録（パスワードは `password`、メール認証済み）
* **AttendanceSeeder（`attendances` テーブル）**
  * **`user1`（`/attendance/report` レポート検証用データ）**: 過去5ヶ月は各月平日15日（計75日）の通常勤務（`09:00:00`〜`18:00:00`）、当月17日は通常勤務（`09:00:00`〜`18:00:00`）10日 / 残業（`09:00:00`〜`20:00:00`）3日 / 遅刻（`09:30:00`〜`18:00:00`）2日 / 早退（`09:00:00`〜`17:00:00`）1日 / 長時間労働（`08:00:00`〜`21:00:00`）1日
  * **`user2`・`user3`（サンプルデータ）**: 3ヶ月前の月初から本日までの平日（各月15日まで、および本日分）に `09:00:00` 出勤のデータを登録（本日分は退勤未打刻 `null`、それ以外は `18:00:00` 退勤）
* **RestSeeder（`rests` テーブル）**
  * **登録内容**: 全勤怠レコード（`attendances`）に対して、固定休憩（`12:00:00`〜`13:00:00` / 1時間）を付与
* **RequestSeeder（`requests` / `request_rests` テーブル）**
  * **登録内容**: `user1` の最新勤怠レコードを対象に、打刻修正申請（`08:50:00`〜`18:00:00`、`approval_status = true`）および休憩修正データ（`12:00:00`〜`12:45:00`）を登録

## 使用技術
本プロジェクトで利用している主要な技術・ツール一覧です。

* **OS**: Dockerが動作する任意のOS
* **Backend**: PHP 8.2、Laravel 10.x（Laravel Sail）
* **Database**: MySQL 8.4
* **メールテスト / 開発補助**: Mailpit、phpMyAdmin
* **Webサーバー**: Nginx
* **Frontend**: Vite、Tailwind CSS ^3.4.0、Alpine.js
* **品質・開発ツール**: Docker、Laravel Sail、Laravel Pint

## APIエンドポイント一覧 (V1)
Sanctum認証・Policy認可付きの公開API（`/api/v1/attendance-records`）です。

* **勤怠レコード一覧取得**
  * **メソッド**: `GET`
  * **パス**: `/api/v1/attendance-records`
* **勤怠レコード詳細取得**
  * **メソッド**: `GET`
  * **パス**: `/api/v1/attendance-records/{id}`
* **勤怠レコード作成**
  * **メソッド**: `POST`
  * **パス**: `/api/v1/attendance-records`
* **勤怠レコード更新**
  * **メソッド**: `PUT` / `PATCH`
  * **パス**: `/api/v1/attendance-records/{id}`
* **勤怠レコード削除**
  * **メソッド**: `DELETE`
  * **パス**: `/api/v1/attendance-records/{id}`

## 開発環境URL
ローカル環境で起動した際にアクセスするURL一覧です。

* **アプリケーションURL**: `http://localhost`
* **phpMyAdmin**: `http://localhost:8080`
* **Mailpit**: `http://localhost:8025`

## 作成者
* Harada Naoki




