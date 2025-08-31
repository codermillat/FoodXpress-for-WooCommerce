# Project Workspace Rules: FoodXpress

## Brief overview
This document outlines the complete set of standards, protocols, and workflows for the FoodXpress project. These rules are authoritative and designed to ensure high-quality, maintainable, and cost-efficient development.

## 1. Guiding Engineering Principles
These are the core philosophies that guide all development.

*   **Plan Before Building:** Always take the time to understand requirements and architect a solution before writing code.
*   **Simplicity Over Complexity:** Always seek the simplest possible solution that effectively solves the problem. Avoid premature optimization.
*   **Embrace the DRY Principle (Don't Repeat Yourself):** Relentlessly identify and eliminate code duplication. Favor creating reusable abstractions (functions, classes, components) over copy-pasting.
*   **Adhere to SOLID Principles:** Keep the five SOLID principles (Single Responsibility, Open/Closed, Liskov Substitution, Interface Segregation, Dependency Inversion) at the forefront of all architectural decisions.
*   **Build for the Future:** Write code with the assumption that it will need to be changed. Prioritize maintainability and scalability from the start.

## 2. Code Style & Readability
This section defines our coding style.

*   **Self-Documenting Code:** Your primary goal is to write code that is so clear it requires minimal comments. Use descriptive names. Comments should explain the *why*, not the *what*.
*   **Naming Conventions:** All variables and function names must use `camelCase`. Class names must use `PascalCase`.
*   **Function Simplicity:** Functions should be small and adhere to the Single Responsibility Principle.

## 3. Project Context: The Memory Bank
This protocol ensures continuity and shared knowledge.

*   **Initialization:** At the start of any task, if the `/memory-bank` directory does not exist, trigger the "Initialize Memory Bank" process immediately.
*   **Update Protocol:** After any significant change (new feature, refactor), you must update the relevant Memory Bank documents to reflect the new state of the project.

## 4. Task Execution Workflow (Cost-Aware)
This workflow must be followed at the start of every task to ensure efficiency.

1.  **Start with the Index:** Always read `memory-bank/ProjectIndex.md` first to gain high-level context.
2.  **Targeted Reading:** Based on the index, read only the most relevant `memory-bank` documents needed for the specific task. Avoid reading the entire memory bank.
3.  **Consult MCPs:** Before reading source code, use MCPs like Perplexity or Context7 if the task involves external libraries or APIs for which we don't have direct documentation links.
4.  **Minimal Code Reading:** Only after consulting the Memory Bank and MCPs, read the absolute minimum number of source code files required to complete the task.

## 5. Model & Cost Optimization
This rule governs model selection to manage costs.

*   **Right Model for the Job:** For routine coding and implementation tasks (Act Mode), prioritize cost-effective models like `claude-3-haiku` via OpenRouter. For complex architectural planning, brainstorming, and analysis (Plan Mode), utilize premium models like `gemini-2.5-pro`.
