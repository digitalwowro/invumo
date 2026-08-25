import { render, screen } from "@testing-library/react"
import { describe, expect, it } from "vitest"
import { Table, TableBody, TableCell, TableRow } from "@/components/ui/table"

describe("Table", () => {
  it("contains wide content inside an accessible scroll region", () => {
    render(
      <Table aria-label="Invoices">
        <TableBody>
          <TableRow>
            <TableCell>
              unbroken-content-that-must-wrap-inside-the-table-column
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    )

    expect(screen.getByRole("region", { name: "Invoices" })).toHaveClass(
      "min-w-0",
      "max-w-full",
      "overflow-x-auto"
    )
    expect(screen.getByRole("region", { name: "Invoices" })).toHaveAttribute(
      "tabindex",
      "0"
    )
    expect(screen.getByRole("table", { name: "Invoices" })).toHaveClass(
      "table-auto"
    )
    expect(screen.getByRole("cell")).toHaveClass("[overflow-wrap:anywhere]")
  })
})
