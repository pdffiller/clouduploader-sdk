# Tests

Unit tests for the CloudUploader SDK, written with
[Codeception 5](https://codeception.com/) (PHPUnit 9 under the hood).

The suite is hermetic: no network calls, no credentials, no Dropbox/Google/Box
account needed. Everything runs in well under a second.

## Install

```bash
composer install
```

## Run

```bash
composer test                       # whole suite
./vendor/bin/codecept run Unit      # same thing, explicit suite
```

A single file, a single test, or a name filter:

```bash
./vendor/bin/codecept run Unit UploadModels/DropBoxModelTest.php
./vendor/bin/codecept run Unit UploadModels/DropBoxModelTest.php:testUploadFileReturnsFileIdOnSuccess
./vendor/bin/codecept run Unit --filter uploadFile
```

Useful flags: `--steps` (step by step output), `--debug` (full output and
stack traces), `-f` (stop on first failure).

## Coverage

Coverage needs a driver (Xdebug or PCOV); without one Codeception still runs
the tests and just reports no coverage.

```bash
composer test-coverage              # writes tests/_output/coverage.xml + coverage/
XDEBUG_MODE=coverage ./vendor/bin/codecept run --coverage --coverage-text
```

`codeception.yml` limits coverage to the four SDK directories: `Fabric/`,
`HttpReceiver/`, `Interfaces/`, `UploadModels/`.

## Layout

```
tests/
├── Unit/
│   ├── HttpReceiverTest.php                  # HttpReceiver/HttpReceiver.php
│   ├── ServiceFabricTest.php                 # Fabric/ServiceFabric.php
│   ├── UploadServiceInterfaceTest.php        # Interfaces/UploadServiceInterface.php
│   └── UploadModels/
│       ├── BoxModelTest.php                  # UploadModels/BoxModel.php
│       ├── DropBoxModelTest.php              # UploadModels/DropBoxModel.php
│       └── GoogleDriveModelTest.php          # UploadModels/GoogleDriveModel.php
├── Support/
│   ├── SuperglobalTestCase.php               # base case: $_REQUEST/$_GET isolation, reflection helper
│   ├── UnitTester.php
│   ├── Doubles/
│   │   ├── ArrayPersistentDataStore.php      # session-free Dropbox CSRF store
│   │   ├── FakeDropboxHttpClient.php         # queued responses + recorded requests
│   │   └── ThrowingConfig.php                # ArrayAccess that throws, to reach catch branches
│   └── Helper/Unit.php
└── _data/fixtures/sample.txt                 # upload payload
```

## How each source file is covered

| Source file | Approach |
| --- | --- |
| `HttpReceiver/HttpReceiver.php` | Direct: both casts, missing keys, unknown type, XSS payloads. |
| `Fabric/ServiceFabric.php` | Every routing branch, every guard clause, and the `catch` branches via `ThrowingConfig`. |
| `Interfaces/UploadServiceInterface.php` | Reflection contract test: the interface shape and all three models against it. |
| `UploadModels/DropBoxModel.php` | End to end — the SDK accepts `http_client_handler` and `persistent_data_store`, so auth URL, OAuth exchange, CSRF validation, upload, account lookup and failure paths all run against fakes. |
| `UploadModels/GoogleDriveModel.php` | Auth URL, `getGoogleConfig()`, guard clauses and the private `getExtension()`/`getMime()` helpers. |
| `UploadModels/BoxModel.php` | Auth URL, the `Not implemented` contract, and the private `getBox()`/`getExtension()` helpers. |

### Known gaps

`GoogleDriveModel` and `BoxModel` build their transports inline
(`new \Google\Client()`, `new \Apibox\Apibox()` + cURL), so the API-facing
paths — Drive upload/update/metadata/folder lookup, Box upload and token
exchange — cannot be reached without hitting the network. Covering those needs
an injection seam (pass in a client, or a factory), which is a source change
rather than a test change. `DropBoxModel` already has that seam and is
therefore covered in full.

`BoxModelTest::testGetExtensionStripsQueryStringAndRaisesNotice` pins a latent
bug: `reset(explode(...))` passes a function result by reference and raises
`Only variables should be passed by reference` on every call. The same line
exists in all three models (`BoxModel.php:87`, `DropBoxModel.php:176`,
`GoogleDriveModel.php:284`).

## CI

`.github/workflows/tests.yml` runs the suite on every push and pull request to
`master`, across PHP 8.1-8.5, plus one job that verifies the committed
`composer.lock`. It needs no secrets and requests only `contents: read`.
