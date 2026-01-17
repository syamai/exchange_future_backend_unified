# Development environment

## Coding Style

This project follows [Google Java Style Guide](https://google.github.io/styleguide/javaguide.html)

### IntelliJ

1. Import `intellij-java-google-style.xml` into your IDE `Editor -> Code Style`
2. Install IntelliJ [google-java-format plugin](https://plugins.jetbrains.com/plugin/8527-google-java-format)

## Commit convention

This project follows [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/)

The commit message should be structured as follows:

```text
<type>[optional scope]: <description>

[optional body]

[optional footer(s)]
```

Read the link for more detail guideline.

## Setup environment

1. Install:
   * jdk 17
   * maven 3.8.x
   * python & [pre-commit](https://pre-commit.com) (Optional: for git hooks support)
2. Install `pre-commit` git hooks

```bash
$ pre-commit install
$ pre-commit install --hook-type commit-msg
```

## Testing

```bash
$ mvn clean verify
```

## Packaging

TBD

