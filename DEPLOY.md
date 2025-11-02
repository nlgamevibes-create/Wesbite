# Deployment Guide - GameFlux Website

## 📤 Upload naar GitHub

### Stap 1: Git Setup (eerste keer)

```bash
# In de website map
cd "C:\Users\milan\Desktop\gameflux - Website"

# Git initialiseren
git init

# Remote toevoegen
git remote add origin https://github.com/nlgamevibes-create/Wesbite.git

# Bestanden toevoegen
git add .

# Commit maken
git commit -m "Initial commit - GameFlux website"

# Pushen naar GitHub
git branch -M main
git push -u origin main
```

### Stap 2: Toekomstige Updates

```bash
git add .
git commit -m "Beschrijving van wijzigingen"
git push
```

## ⚠️ Belangrijk: API Keys

**Let op:** De PHP bestanden bevatten Stripe API keys. Voor productie:

1. **Gebruik environment variables** op je server
2. **Of maak een `.env` bestand** dat niet in Git komt
3. **Of gebruik server-specifieke config** bestanden

## 🌐 Upload naar Webserver

### Via FTP/SFTP:
1. Upload alle bestanden naar `website.gameflux.nl`
2. Zorg dat PHP werkt
3. Test de checkout flow

### Via Git Deploy (als je server Git support heeft):
```bash
# Op je server
git clone https://github.com/nlgamevibes-create/Wesbite.git
cd Wesbite
# Configureer API keys lokaal op server
```

## ✅ Checklist voor Deployment

- [ ] Alle bestanden geüpload
- [ ] Stripe keys geconfigureerd op server
- [ ] Email adressen aangepast
- [ ] PHP werkt (test: `php -v`)
- [ ] cURL extensie geïnstalleerd
- [ ] HTTPS certificaat actief
- [ ] Stripe webhook geconfigureerd
- [ ] Test betaling gedaan

