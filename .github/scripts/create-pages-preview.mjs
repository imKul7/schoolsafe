import fs from "node:fs"
import path from "node:path"

const root = path.resolve(".")
const manifestPath = path.join(
  root,
  "public/build/manifest.json"
)

if (!fs.existsSync(manifestPath)) {
  throw new Error(
    "Manifest Vite tidak ditemukan. Jalankan npm run build terlebih dahulu."
  )
}

const manifest = JSON.parse(
  fs.readFileSync(manifestPath, "utf8")
)

const appEntry = manifest["resources/js/app.tsx"]

if (!appEntry) {
  throw new Error(
    "Entry resources/js/app.tsx tidak ditemukan dalam manifest."
  )
}

const outputDirectory = path.join(
  root,
  "gh-pages-dist"
)

fs.rmSync(outputDirectory, {
  recursive: true,
  force: true,
})

fs.mkdirSync(outputDirectory, {
  recursive: true,
})

fs.cpSync(
  path.join(root, "public/build"),
  path.join(outputDirectory, "build"),
  {
    recursive: true,
  }
)

for (const filename of [
  "favicon.ico",
  "logo.svg",
  "robots.txt",
]) {
  const source = path.join(root, "public", filename)

  if (fs.existsSync(source)) {
    fs.copyFileSync(
      source,
      path.join(outputDirectory, filename)
    )
  }
}

const initialPage = {
  component: "welcome",
  props: {
    errors: {},
    auth: {
      user: null,
    },
  },
  url: "/schoolsafe/",
  version: null,
  clearHistory: false,
  encryptHistory: false,
}

const pageData = JSON.stringify(initialPage)
  .replaceAll("&", "&amp;")
  .replaceAll("'", "&#39;")
  .replaceAll("<", "&lt;")
  .replaceAll(">", "&gt;")

const cssLinks = (appEntry.css || [])
  .map(
    (file) =>
      `<link rel="stylesheet" href="/schoolsafe/build/${file}">`
  )
  .join("\n")

const html = `<!doctype html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
  >
  <meta
    name="description"
    content="Preview SchoolSafe, sistem penjemputan siswa berbasis Laravel dan React."
  >
  <title>SchoolSafe - Smart Pickup System</title>
  <link
    rel="icon"
    href="/schoolsafe/favicon.ico"
  >
  ${cssLinks}
</head>
<body>
  <div
    id="app"
    data-page='${pageData}'
  ></div>

  <script>
    window.route = function (name) {
      if (name === "login") {
        return "/schoolsafe/login";
      }

      return "/schoolsafe/";
    };

    document.addEventListener(
      "click",
      function (event) {
        const link = event.target.closest("a");

        if (!link) {
          return;
        }

        const href = link.getAttribute("href") || "";

        if (
          href === "/schoolsafe/login" ||
          href.endsWith("/login")
        ) {
          event.preventDefault();
          event.stopImmediatePropagation();

          alert(
            "Ini adalah preview statis SchoolSafe. Fitur login dan dashboard memerlukan server Laravel."
          );
        }
      },
      true
    );
  </script>

  <script
    type="module"
    src="/schoolsafe/build/${appEntry.file}"
  ></script>
</body>
</html>
`

fs.writeFileSync(
  path.join(outputDirectory, "index.html"),
  html,
  "utf8"
)

fs.writeFileSync(
  path.join(outputDirectory, ".nojekyll"),
  "",
  "utf8"
)

fs.writeFileSync(
  path.join(outputDirectory, "404.html"),
  html,
  "utf8"
)

console.log(
  "Preview statis SchoolSafe berhasil dibuat."
)
