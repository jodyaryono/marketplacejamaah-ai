#!/usr/bin/env python3
with open("/var/www/integrasi-wa.jodyaryono.id/index.js") as f:
    c = f.read()
idx = c.find("approve-membership", c.find("GROUP MEMBERSHIP APPROVAL"))
print(repr(c[idx:idx+700]))
