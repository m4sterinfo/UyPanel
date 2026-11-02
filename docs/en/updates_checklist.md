<h1 align="center">✅ UyPanel Release Preparation Checklist</h1>

<p align="center">
  This document describes the process of creating an <b>UyPanel</b> release — a step-by-step guide for developers on updating the version, building archives, and publishing on GitHub.
</p>

---

## 📚 Navigation

* [🔢 1. Update Version](#1-update-version)
* [🧹 2. Deleted Files](#2-deleted-files)
* [⚙️ 3. Build Archives](#3-build-archives)
* [📝 4. Changelog](#4-changelog)
* [🚀 5. GitHub Release](#5-github-release)

---

## 🔢 1. Update Version

* Set the **new `UyPanel_VERSION` value** in the following files:

**File to edit:**

```
src/core/Config/AppConfig.php
```

**Auto-update command:**

```bash
sed -i "s/define('UyPanel_VERSION', *'[0-9]\+\.[0-9]\+\.[0-9]\+');/define('UyPanel_VERSION', 'X.Y.Z');/" src/core/Config/AppConfig.php
```

**Commit the changes with a message:**

```bash
git add .
git commit -m "Bump version to X.Y.Z"
git push
```

> 💡 **Tip:** Replace `X.Y.Z` with the actual version, e.g., `1.2.3`.

---

## 🧹 2. Deleted Files

* Generate a list of deleted files:

```bash
make delete_files_list
```

* Open the file `dist/deleted_files.txt`.
* Copy the contents to `src/cli/Commands/UpdateCommand.php` in the `$rCleanupFiles` array inside the `post-update` case.

> ⚠️ **Important:** Ensure paths are correct to avoid deleting critical files.

**Commit changes with a message:**

```bash
git add .
git commit -m "Added deletion of old files before release"
git push
```

---

## ⚙️ 3. Build Archives

> 🤖 **Automated:** Building is handled by GitHub Actions workflow `.github/workflows/build-release.yml` when a release is published. Assets are automatically attached to the release.

For **local builds**, run the following commands:

```bash
make new
make lb
make main
make main_update
make lb_update
```

After building, `dist/` should contain:

  - `loadbalancer.tar.gz` — LB installation archive
  - `loadbalancer_update.tar.gz` — LB update archive
  - `UyPanel.zip` — MAIN installation archive
  - `update.tar.gz` — MAIN update archive
  - `hashes.md5` — file with checksums

> 🧰 **Check:** After building, verify archive integrity with `md5sum -c hashes.md5`.

---

## 📝 4. Changelog

Fetch the previous release tag via GitHub API and generate the changelog:

```bash
PREV_TAG=$(curl -s https://api.github.com/repos/m4sterinfo/UyPanel/releases/latest \
  | grep -Po '"tag_name":\s*"\K[^"]+')
echo "Previous release: $PREV_TAG"
git log --pretty=format:"- %s (%h)" "$PREV_TAG"..main > dist/changes.md
```

* **Then add current release changes** using this JSON:

[https://github.com/m4sterinfo/UyPanel_Update/blob/main/changelog.json](https://github.com/m4sterinfo/UyPanel_Update/blob/main/changelog.json)

* Add current release changes in JSON format:

```json
[
  {
      "version": "X.Y.Z",
      "changes": [
        "Description of change 1",
        "Description of change 2"
      ]
  }
]
```

> 💬 **Recommendation:** Keep descriptions concise and informative, focusing on key improvements and fixes.

---

## 🚀 5. GitHub Release

* Create a new release on [GitHub Releases](https://github.com/m4sterinfo/UyPanel/releases) **without attaching files**.
* Include the changelog in the release description.
* After publishing, GitHub Actions will automatically build and attach:

  - `loadbalancer.tar.gz` — LB installation archive
  - `loadbalancer_update.tar.gz` — LB update archive
  - `UyPanel.zip` — MAIN installation archive
  - `update.tar.gz` — MAIN update archive
  - `hashes.md5` — file with checksums

> ✅ **Completion:** Wait for the workflow to finish (Actions tab), then verify that all files are downloadable and checksums match.

---