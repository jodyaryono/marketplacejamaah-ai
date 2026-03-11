#!/usr/bin/env python3
import json
import urllib.request

data = json.dumps({
    'group_id': '6285719195627-1540340459@g.us',
    'requester': '628123456789'
}).encode()

req = urllib.request.Request(
    'http://localhost:3001/api/approve-membership',
    data=data,
    headers={
        'Content-Type': 'application/json',
        'Authorization': 'Bearer fc42fe461f106cdee387e807b972b52b'
    },
    method='POST'
)
try:
    resp = urllib.request.urlopen(req)
    print(resp.read().decode())
except urllib.error.HTTPError as e:
    print('HTTP Error:', e.code, e.read().decode())
