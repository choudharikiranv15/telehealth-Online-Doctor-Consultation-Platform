# 🔐 Setup Multiple GitHub Accounts - Personal vs Work

Complete guide to use different GitHub accounts in different IDEs/tools without conflicts.

---

## 🎯 Your Situation

- **VS Code**: Uses work GitHub account (default)
- **Trae/Cursor/Other IDEs**: Should use personal GitHub account
- **Requirement**: Changes shouldn't affect each other

---

## ✅ Solution: Per-Directory Git Configuration

Git allows you to set different configurations per directory!

---

## 🛠️ Step-by-Step Setup

### Step 1: Check Current Global Git Config

Open terminal/command prompt:

```bash
git config --global user.name
git config --global user.email
```

This shows your current default (probably work account).

---

### Step 2: Create SSH Keys for Both Accounts

#### For Work Account (if not already done):

```bash
ssh-keygen -t ed25519 -C "your-work-email@company.com" -f ~/.ssh/id_ed25519_work
```

#### For Personal Account:

```bash
ssh-keygen -t ed25519 -C "your-personal-email@gmail.com" -f ~/.ssh/id_ed25519_personal
```

Press Enter for no passphrase (or set one if you prefer).

**Windows users**: Replace `~/.ssh/` with `C:\Users\YourUsername\.ssh\`

---

### Step 3: Add SSH Keys to SSH Agent

#### Windows:

```bash
# Start SSH agent
eval "$(ssh-agent -s)"

# Add work key
ssh-add ~/.ssh/id_ed25519_work

# Add personal key
ssh-add ~/.ssh/id_ed25519_personal
```

#### Windows (PowerShell):

```powershell
# Start SSH agent
Start-Service ssh-agent

# Add work key
ssh-add C:\Users\YourUsername\.ssh\id_ed25519_work

# Add personal key
ssh-add C:\Users\YourUsername\.ssh\id_ed25519_personal
```

---

### Step 4: Add SSH Keys to GitHub Accounts

#### For Work GitHub:

1. Copy work public key:
   ```bash
   cat ~/.ssh/id_ed25519_work.pub
   # Windows: type C:\Users\YourUsername\.ssh\id_ed25519_work.pub
   ```

2. Go to **Work GitHub** → Settings → SSH and GPG keys → New SSH key
3. Paste the key and save

#### For Personal GitHub:

1. Copy personal public key:
   ```bash
   cat ~/.ssh/id_ed25519_personal.pub
   # Windows: type C:\Users\YourUsername\.ssh\id_ed25519_personal.pub
   ```

2. Go to **Personal GitHub** → Settings → SSH and GPG keys → New SSH key
3. Paste the key and save

---

### Step 5: Create SSH Config File

Create/edit `~/.ssh/config` file:

**Windows**: `C:\Users\YourUsername\.ssh\config`

```ssh-config
# Work GitHub Account
Host github.com-work
    HostName github.com
    User git
    IdentityFile ~/.ssh/id_ed25519_work
    IdentitiesOnly yes

# Personal GitHub Account
Host github.com-personal
    HostName github.com
    User git
    IdentityFile ~/.ssh/id_ed25519_personal
    IdentitiesOnly yes
```

**Windows version** (adjust paths):

```ssh-config
# Work GitHub Account
Host github.com-work
    HostName github.com
    User git
    IdentityFile C:\Users\YourUsername\.ssh\id_ed25519_work
    IdentitiesOnly yes

# Personal GitHub Account
Host github.com-personal
    HostName github.com
    User git
    IdentityFile C:\Users\YourUsername\.ssh\id_ed25519_personal
    IdentitiesOnly yes
```

---

### Step 6: Configure Git for Different Directories

#### Option A: Directory-Specific Config (Recommended)

Create a global `.gitconfig` with conditional includes:

Edit `~/.gitconfig` (Windows: `C:\Users\YourUsername\.gitconfig`):

```ini
[user]
    name = Your Work Name
    email = your-work-email@company.com

# Conditional config for personal projects
[includeIf "gitdir:D:/personal-projects/"]
    path = ~/.gitconfig-personal

[includeIf "gitdir:D:/xampp/htdocs/telehealth/"]
    path = ~/.gitconfig-personal
```

Then create `~/.gitconfig-personal` (Windows: `C:\Users\YourUsername\.gitconfig-personal`):

```ini
[user]
    name = Your Personal Name
    email = your-personal-email@gmail.com
```

#### Option B: Set Config Per Repository (Manual)

For each personal project, run:

```bash
cd D:\xampp\htdocs\telehealth

git config user.name "Your Personal Name"
git config user.email "your-personal-email@gmail.com"
```

This only affects this repository.

---

### Step 7: Update Remote URLs

#### For Personal Projects:

Change remote URL to use personal SSH host:

```bash
cd D:\xampp\htdocs\telehealth

# Check current remote
git remote -v

# Change to personal account
git remote set-url origin git@github.com-personal:your-username/telehealth.git
```

#### For Work Projects:

```bash
cd D:\work\project-name

# Change to work account
git remote set-url origin git@github.com-work:company/project-name.git
```

---

### Step 8: Test Your Setup

#### Test Personal Account:

```bash
ssh -T git@github.com-personal
```

Expected output:
```
Hi your-personal-username! You've successfully authenticated...
```

#### Test Work Account:

```bash
ssh -T git@github.com-work
```

Expected output:
```
Hi your-work-username! You've successfully authenticated...
```

---

## 🎯 IDE-Specific Configuration

### For VS Code (Keep Work Account)

VS Code will use the git config of the directory you open.

1. Open work projects in VS Code
2. They'll automatically use work account (default global config)

### For Trae/Cursor/Other IDEs (Use Personal)

1. Open personal projects in these IDEs
2. They'll automatically use personal account (from conditional config)

### Per-Project Override

Each IDE respects the repository's local git config, so:
- Work projects → Work account (automatic)
- Personal projects → Personal account (automatic with our setup)

---

## 📋 Complete Configuration Example

### Global Config (`~/.gitconfig`):

```ini
[user]
    name = John Doe (Work)
    email = john.doe@company.com

[includeIf "gitdir:D:/personal/"]
    path = ~/.gitconfig-personal

[includeIf "gitdir:D:/xampp/htdocs/"]
    path = ~/.gitconfig-personal

[core]
    autocrlf = true
    editor = code --wait

[init]
    defaultBranch = main
```

### Personal Config (`~/.gitconfig-personal`):

```ini
[user]
    name = John Doe
    email = john.personal@gmail.com
```

### SSH Config (`~/.ssh/config`):

```ssh-config
# Work GitHub
Host github.com-work
    HostName github.com
    User git
    IdentityFile ~/.ssh/id_ed25519_work
    IdentitiesOnly yes

# Personal GitHub
Host github.com-personal
    HostName github.com
    User git
    IdentityFile ~/.ssh/id_ed25519_personal
    IdentitiesOnly yes

# Default GitHub (fallback to work)
Host github.com
    HostName github.com
    User git
    IdentityFile ~/.ssh/id_ed25519_work
    IdentitiesOnly yes
```

---

## 🔍 Verify Your Setup

### Check Which Account a Repo Uses:

```bash
cd your-project-folder

# Check user name
git config user.name

# Check user email
git config user.email

# Check remote URL
git remote -v
```

### Test Commit:

```bash
# Make a test commit
git commit --allow-empty -m "Test commit"

# Check author
git log -1 --pretty=format:"%an <%ae>"
```

---

## 🚀 Quick Setup Script (Windows)

Save this as `setup-github-accounts.bat`:

```batch
@echo off
echo Setting up multiple GitHub accounts...

REM Generate SSH keys
ssh-keygen -t ed25519 -C "work@company.com" -f %USERPROFILE%\.ssh\id_ed25519_work
ssh-keygen -t ed25519 -C "personal@gmail.com" -f %USERPROFILE%\.ssh\id_ed25519_personal

REM Start SSH agent and add keys
start-ssh-agent
ssh-add %USERPROFILE%\.ssh\id_ed25519_work
ssh-add %USERPROFILE%\.ssh\id_ed25519_personal

echo.
echo Next steps:
echo 1. Add public keys to GitHub accounts
echo 2. Create SSH config file
echo 3. Update git global config
echo.
echo Work public key:
type %USERPROFILE%\.ssh\id_ed25519_work.pub
echo.
echo Personal public key:
type %USERPROFILE%\.ssh\id_ed25519_personal.pub

pause
```

---

## 🆘 Troubleshooting

### Issue: "Permission denied (publickey)"

**Solution:**
```bash
# Check if keys are loaded
ssh-add -l

# If not, add them
ssh-add ~/.ssh/id_ed25519_work
ssh-add ~/.ssh/id_ed25519_personal
```

### Issue: Wrong account being used

**Solution:**
```bash
# Check repository config
git config user.email

# Set explicitly
git config user.email "correct-email@domain.com"

# Check remote URL uses correct host
git remote -v
git remote set-url origin git@github.com-personal:user/repo.git
```

### Issue: SSH agent not starting on Windows

**Solution:**
```powershell
# Run as Administrator
Get-Service ssh-agent | Set-Service -StartupType Automatic
Start-Service ssh-agent
```

### Issue: IDE using wrong account

**Solution:**
1. Close IDE completely
2. Verify repository config: `git config user.email`
3. Restart IDE
4. Check IDE's git integration settings

---

## 📝 Quick Reference Commands

```bash
# Check current user
git config user.name
git config user.email

# Set local repo user
git config user.name "Name"
git config user.email "email@domain.com"

# Check remote
git remote -v

# Change remote to personal
git remote set-url origin git@github.com-personal:user/repo.git

# Change remote to work
git remote set-url origin git@github.com-work:company/repo.git

# Test SSH connection
ssh -T git@github.com-personal
ssh -T git@github.com-work

# List SSH keys
ssh-add -l
```

---

## ✅ Final Checklist

For Your TeleHealth Project (Personal):

```bash
cd D:\xampp\htdocs\telehealth

# 1. Set personal user
git config user.name "Your Personal Name"
git config user.email "your-personal@gmail.com"

# 2. Update remote URL (if you have GitHub repo)
git remote set-url origin git@github.com-personal:yourusername/telehealth.git

# 3. Verify
git config user.email
git remote -v

# 4. Test
ssh -T git@github.com-personal
```

---

## 🎯 Summary

**What We Did:**
1. ✅ Created separate SSH keys for work and personal
2. ✅ Configured SSH to use different keys per host
3. ✅ Set up conditional git config based on directory
4. ✅ Updated remote URLs to use correct SSH host

**Result:**
- **VS Code** (work projects) → Uses work GitHub ✅
- **Trae/Cursor** (personal projects) → Uses personal GitHub ✅
- **No conflicts** between accounts ✅

---

## 📚 Additional Resources

- [GitHub Multiple Accounts Documentation](https://docs.github.com/en/account-and-profile/setting-up-and-managing-your-personal-account-on-github/managing-your-personal-account/managing-multiple-accounts)
- [Git Conditional Includes](https://git-scm.com/docs/git-config#_conditional_includes)
- [SSH Config File](https://linux.die.net/man/5/ssh_config)

---

**You're all set! Your work and personal GitHub accounts are now completely separated.** 🎉
