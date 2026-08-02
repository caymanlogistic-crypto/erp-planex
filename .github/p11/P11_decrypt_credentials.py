#!/usr/bin/env python3
import argparse
import base64
import hashlib
import json
import sys
from pathlib import Path

from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import ed25519, x25519
from cryptography.hazmat.primitives.ciphers.aead import AESGCM
from cryptography.hazmat.primitives.kdf.hkdf import HKDF

INFO = b'P11-runtime-credentials-v1'


def derive_x25519_private(private_key_path: Path) -> x25519.X25519PrivateKey:
    key = serialization.load_ssh_private_key(private_key_path.read_bytes(), password=None)
    if not isinstance(key, ed25519.Ed25519PrivateKey):
        raise ValueError('Expected Ed25519 OpenSSH private key')
    seed = key.private_bytes(
        serialization.Encoding.Raw,
        serialization.PrivateFormat.Raw,
        serialization.NoEncryption(),
    )
    digest = hashlib.sha512(seed).digest()
    scalar = bytearray(digest[:32])
    scalar[0] &= 248
    scalar[31] &= 127
    scalar[31] |= 64
    return x25519.X25519PrivateKey.from_private_bytes(bytes(scalar))


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument('--key', required=True)
    parser.add_argument('--input', required=True)
    args = parser.parse_args()

    envelope = json.loads(Path(args.input).read_text(encoding='utf-8'))
    if envelope.get('version') != 1 or envelope.get('info') != INFO.decode():
        raise ValueError('Unsupported P11 credential envelope')

    private_key = derive_x25519_private(Path(args.key))
    ephemeral = x25519.X25519PublicKey.from_public_bytes(base64.b64decode(envelope['ephemeral_public_key']))
    shared = private_key.exchange(ephemeral)
    key = HKDF(
        algorithm=hashes.SHA256(),
        length=32,
        salt=base64.b64decode(envelope['salt']),
        info=INFO,
    ).derive(shared)
    plaintext = AESGCM(key).decrypt(
        base64.b64decode(envelope['nonce']),
        base64.b64decode(envelope['ciphertext']),
        INFO,
    )
    data = json.loads(plaintext.decode('utf-8'))
    if not isinstance(data, list) or len(data) < 3:
        raise ValueError('Credential payload is incomplete')
    required = {'SUPERADMIN', 'OWNER', 'LOGIST'}
    roles = {str(item.get('role', '')).upper() for item in data if isinstance(item, dict)}
    if not required.issubset(roles):
        raise ValueError('Required role credentials are missing')
    for item in data:
        if not all(isinstance(item.get(field), str) and item[field] for field in ('role', 'username', 'password')):
            raise ValueError('Credential payload contains an invalid item')
    sys.stdout.write(base64.b64encode(plaintext).decode('ascii'))
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
