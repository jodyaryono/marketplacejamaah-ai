path = "/var/www/integrasi-wa.jodyaryono.id/index.js"
with open(path, "r") as f:
    content = f.read()

insert = """        // Download media for image messages and include in webhook payload
        if (msg.hasMedia && msg.type === "image") {
            try {
                const media = await msg.downloadMedia();
                if (media) {
                    payload.media_data = { mimetype: media.mimetype, data: media.data };
                }
            } catch (e) { console.error("[Media][" + phoneId + "]", e.message); }
        }
"""

target = "        const resp = await fetch(wUrl"
if "payload.media_data" in content:
    print("Already patched!")
elif target in content:
    content = content.replace(target, insert + target, 1)
    with open(path, "w") as f:
        f.write(content)
    print("Patched successfully!")
else:
    print("ERROR: target line not found")
