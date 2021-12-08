## Jira Wizard aka Jizzard 👑

### Use cases:

- Create bulk issues in jira

### Usage

#### Bulk issue creation

- Create a file `template.txt` with your issue body.
- Create a file `projects.yml` with the following format:

```yml
projects:
  # One entry for each project key.
  - PROJKEY1
  - PROJKEY2  
```

Run with

```./bin/jizzard cb```

#### CSV loading

- Create a CSV file following the layout in `examples/test.csv`

Run with

```.bin/jizzard cc PROJECT-KEY /path/to/your.csv```

Where PROJECT-KEY is the project key, e.g. if your Jira ticket is ABC-123, use ABC
