# VanillaFix

Imported a map and half of it shows up as `?` blocks? That happens because PocketMine-MP and Altay don't implement every vanilla block yet (pistons, droppers, observers, shelves, copper chests, sculk, a lot of newer plants...). The client knows these blocks, the server doesn't, so it can't load them.

VanillaFix fixes that when the world loads. Every block the server can't read gets swapped for the closest vanilla block it does support, so your spawn and builds look right again without touching the map files.

## What it does

- Finds every block the client knows but your server software can't load, and picks a sensible replacement (logs become oak logs, carpets stay carpets, plants disappear instead of turning into stone, and so on).
- Lets you override any replacement in `config.yml`.
- Checks every replacement on startup, so a typo never breaks a world.
- `/vanillafix` shows how many blocks were repaired since startup and which ones.
- `/vanillafix scan` lists every block type your server doesn't support.

## Install

1. Drop the plugin in your `plugins` folder.
2. Restart the server.

That's it. The default config already covers what we found on Altay.

## Config

```yaml
auto-detect: true
fallback: stone

replacements:
  "minecraft:observer": polished_andesite
  "minecraft:piston": smooth_stone
  "minecraft:dropper": cobblestone
```

- `auto-detect`: handle unsupported blocks that aren't listed below.
- `fallback`: used when no better guess exists.
- `replacements`: block id on the left, any block name on the right (`air` removes the block).

## Commands

| Command | Permission | Default |
|---|---|---|
| `/vanillafix` | `vanillafix.command` | op |
| `/vanillafix scan` | `vanillafix.command` | op |

## Good to know

The swap happens when a chunk is read. Once that chunk is saved, the replacement is what ends up on disk, so the original block is gone for good. Keep a backup of your world if you think you'll want the originals back later.

## Compatibility

https://discord.gg/zH3ykunKd3

PocketMine-MP API 5 and Altay.

## About

Made by DeepSlate Dev. Released under the MIT license, free to use on any server.
